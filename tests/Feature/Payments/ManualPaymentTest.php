<?php

namespace Tests\Feature\Payments;

use App\Exceptions\ManualPaymentException;
use App\Exceptions\SocialBookingException;
use App\Models\Company\OneOffAccess;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Social\SocialBooking;
use App\Services\ManualPaymentService;
use App\Services\RedeItauService;
use App\Services\SchedulesService;
use App\Services\Social\SocialBookingService;
use App\Services\Social\SocialCounterBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * Baixa manual de pagamento (pago fora da plataforma) e a reserva social
 * fechada de uma vez no balcão.
 *
 * Quadra: o agendamento pendente vira confirmado, o confirmado no balcão
 * ganha o pagamento; nunca o que já tem pagamento, o cancelado ou o de dois
 * sócios juntos. Espaço social: só a reserva aprovada, pelo total exato, e o
 * efeito é o mesmo do pagamento online (confirmada + convidados na portaria).
 */
class ManualPaymentTest extends TestCase
{
    use CreatesSocialBookingSchema;

    private const TODAY = '2026-10-19 10:00:00';
    private const EVENT = '2026-11-14';

    private Place $court;
    private Place $hall;
    private int $ana;
    private int $bruno;

    /** @var \Mockery\MockInterface */
    private $schedulesService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(self::TODAY);
        $this->createSocialBookingSchema();

        $sports = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        $this->court = Place::create(['name' => 'Quadra 1', 'place_group_id' => $sports->id, 'status_id' => 1, 'price' => 80]);

        $social = PlaceGroup::create(['name' => 'Salões', 'category' => PlaceGroup::CATEGORY_SOCIAL, 'minimum_antecedence' => 2, 'maximum_antecedence' => 180, 'social_payment_days' => 7]);
        $social->weekdays()->attach(range(1, 7));
        $this->hall = Place::create([
            'name' => 'Salão Estrela', 'place_group_id' => $social->id, 'status_id' => 1, 'price' => 390,
            'capacity' => 70, 'max_guests' => 30, 'extra_guests' => 5, 'extra_guest_price' => 15,
        ]);

        $this->ana = DB::table('members')->insertGetId(['cpf' => '39053344705', 'name' => 'Ana', 'email' => 'ana@teste.local', 'title' => '1001']);
        $this->bruno = DB::table('members')->insertGetId(['cpf' => '86288366757', 'name' => 'Bruno', 'email' => 'bruno@teste.local', 'title' => '1002']);
        DB::table('users')->insert(['id' => 9, 'name' => 'Secretaria']);

        // O e-mail de quadra é do SchedulesService (que monta EmailService de
        // verdade no construtor): aqui só se confere QUEM recebe.
        $this->schedulesService = Mockery::mock(SchedulesService::class);
        $this->app->instance(SchedulesService::class, $this->schedulesService);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payments(): ManualPaymentService
    {
        return app(ManualPaymentService::class);
    }

    private function courtSchedule(int $status, ?int $memberId = null, string $start = '2026-10-20 18:00:00', ?int $createdBy = null): Schedule
    {
        return Schedule::create([
            'place_id' => $this->court->id,
            'member_id' => $memberId ?? $this->ana,
            'start_schedule' => $start,
            'end_schedule' => Carbon::parse($start)->addHour(),
            'status_id' => $status,
            'price' => 80,
            'created_by_user' => $createdBy,
        ]);
    }

    private function assertRefused(string $needle, callable $action): void
    {
        try {
            $action();
            $this->fail("Esperava a recusa: {$needle}");
        } catch (ManualPaymentException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    private function socialRequest(): SocialBooking
    {
        return app(SocialBookingService::class)->request($this->hall, $this->ana, [
            'date' => self::EVENT,
            'event_type' => 'Aniversário',
            'guests' => [['name' => 'Carlos Convidado', 'cpf' => '529.982.247-25']],
        ], 9);
    }

    public function test_quadra_pendente_vira_confirmada_com_pagamento_manual(): void
    {
        $pending = $this->courtSchedule(3);
        $this->schedulesService->shouldReceive('notifyScheduleStatus')->once()
            ->withArgs(fn ($schedules, $payment) => $schedules->pluck('id')->all() === [$pending->id] && $payment instanceof SchedulePayment)
            ->andReturnTrue();

        $payment = $this->payments()->record([$pending->id], ['payment_method' => 'manual_pix', 'notes' => 'Pix E123'], 9);

        $this->assertTrue($payment->is_manual);
        $this->assertSame(9, (int) $payment->recorded_by);
        $this->assertSame('Pix E123', $payment->manual_notes);
        $this->assertSame(1, (int) $payment->status_id);
        $this->assertSame(80.0, (float) $payment->paid_amount);
        $this->assertNull($payment->payment_integration_id);
        $this->assertSame('Pix direto ao clube', $payment->methodLabel());
        $this->assertFalse($payment->isOnline());

        $pending->refresh();
        $this->assertSame(1, (int) $pending->status_id);
        $this->assertSame($payment->id, (int) $pending->schedule_payment_id);
    }

    public function test_quadra_do_balcao_ja_confirmada_ganha_o_pagamento_sem_novo_email(): void
    {
        $first = $this->courtSchedule(1, createdBy: 9);
        $second = $this->courtSchedule(1, start: '2026-10-20 19:00:00', createdBy: 9);
        $this->schedulesService->shouldNotReceive('notifyScheduleStatus');

        $payment = $this->payments()->record([$first->id, $second->id], ['payment_method' => 'manual_cash', 'paid_at' => '2026-10-19 09:30'], 9);

        // Sem valor informado, vale a soma dos agendamentos.
        $this->assertSame(160.0, (float) $payment->paid_amount);
        $this->assertSame('2026-10-19 09:30', $payment->paid_at->format('Y-m-d H:i'));
        $this->assertSame(2, Schedule::where('schedule_payment_id', $payment->id)->count());
    }

    public function test_valor_informado_vale_para_quadra(): void
    {
        $schedule = $this->courtSchedule(1, createdBy: 9);
        $payment = $this->payments()->record([$schedule->id], ['payment_method' => 'manual_card', 'amount' => '60.50'], 9);

        $this->assertSame(60.5, (float) $payment->paid_amount);
    }

    public function test_recusas_da_quadra(): void
    {
        $paid = $this->courtSchedule(1);
        $this->payments()->record([$paid->id], ['payment_method' => 'manual_cash'], 9);
        $this->assertRefused('já tem pagamento', fn () => $this->payments()->record([$paid->id], ['payment_method' => 'manual_cash'], 9));

        $cancelled = $this->courtSchedule(0, start: '2026-10-21 18:00:00');
        $this->assertRefused('cancelado ou expirado', fn () => $this->payments()->record([$cancelled->id], ['payment_method' => 'manual_cash'], 9));

        $ana = $this->courtSchedule(1, start: '2026-10-22 18:00:00');
        $bruno = $this->courtSchedule(1, $this->bruno, '2026-10-22 19:00:00');
        $this->assertRefused('sócios diferentes', fn () => $this->payments()->record([$ana->id, $bruno->id], ['payment_method' => 'manual_cash'], 9));

        $this->assertRefused('forma de pagamento', fn () => $this->payments()->record([$ana->id], ['payment_method' => 'credit'], 9));
        $this->assertRefused('futuro', fn () => $this->payments()->record([$ana->id], ['payment_method' => 'manual_cash', 'paid_at' => '2026-10-20 10:00'], 9));
        $this->assertRefused('não encontrado', fn () => $this->payments()->record([999], ['payment_method' => 'manual_cash'], 9));

        // Nenhuma recusa deixou pagamento para trás.
        $this->assertSame(1, SchedulePayment::count());
    }

    public function test_espaco_social_aprovado_e_confirmado_pela_baixa(): void
    {
        $service = app(SocialBookingService::class);
        $booking = $this->socialRequest();
        $service->markContract($booking, 9);
        $booking = $service->approve($booking->fresh(), 40, 'Sócio antigo', 9);
        $this->assertSame(SocialBooking::STAGE_AWAITING_PAYMENT, $booking->stage);

        $this->assertRefused('total aprovado', fn () => $this->payments()->record([$booking->schedule_id], ['payment_method' => 'manual_pix', 'amount' => 390], 9));

        $payment = $this->payments()->record([$booking->schedule_id], ['payment_method' => 'manual_pix', 'amount' => 350], 9);

        $booking->refresh();
        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->stage);
        $this->assertSame(1, (int) $booking->schedule->status_id);
        $this->assertSame($payment->id, (int) $booking->schedule->schedule_payment_id);
        // Mesmo efeito do pagamento online: o convidado está liberado na portaria.
        $this->assertSame(1, OneOffAccess::where('cpf', '52998224725')->whereDate('access_date', self::EVENT)->count());
    }

    public function test_espaco_social_em_analise_nao_recebe_baixa(): void
    {
        $booking = $this->socialRequest();

        $this->assertRefused('aprove antes', fn () => $this->payments()->record([$booking->schedule_id], ['payment_method' => 'manual_cash'], 9));

        $court = $this->courtSchedule(1);
        $this->assertRefused('sozinha', fn () => $this->payments()->record([$booking->schedule_id, $court->id], ['payment_method' => 'manual_cash'], 9));
    }

    public function test_baixa_manual_nao_estorna_pela_rede(): void
    {
        $schedule = $this->courtSchedule(1);
        $payment = $this->payments()->record([$schedule->id], ['payment_method' => 'manual_cash'], 9);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('baixa manual');

        (new RedeItauService())->refundPayment($payment, 10, 9);
    }

    public function test_balcao_fecha_a_reserva_e_manda_para_o_perfil_do_socio_pagar(): void
    {
        $booking = app(SocialCounterBookingService::class)->book($this->hall, $this->ana, [
            'date' => self::EVENT,
            'event_type' => 'Formatura',
            'extra_guests' => 2,
        ], 9, ['discount' => 20, 'discount_reason' => 'Cortesia', 'contract_file' => 'social-contracts/x.pdf']);

        $this->assertSame(SocialBooking::STAGE_AWAITING_PAYMENT, $booking->stage);
        $this->assertSame($this->ana, (int) $booking->member_id);
        $this->assertSame(9, (int) $booking->created_by_user);
        $this->assertNotNull($booking->contract_ok_at);
        $this->assertSame('social-contracts/x.pdf', $booking->contract_file);
        $this->assertSame(9, (int) $booking->approved_by);
        $this->assertNotNull($booking->contacted_at);
        // 390 + 2 × 15 − 20
        $this->assertSame(400.0, (float) $booking->total);
        $this->assertSame('2026-10-26 23:59:59', $booking->payment_due_at->format('Y-m-d H:i:s'));
        $this->assertNull($booking->schedule->schedule_payment_id);
        $this->assertTrue($booking->canPay());
    }

    public function test_balcao_fecha_a_reserva_ja_paga(): void
    {
        $booking = app(SocialCounterBookingService::class)->book($this->hall, $this->ana, [
            'date' => self::EVENT,
            'event_type' => 'Formatura',
            'guests' => [['name' => 'Carlos Convidado', 'cpf' => '52998224725']],
        ], 9, [], ['payment_method' => 'manual_transfer', 'notes' => 'TED 789']);

        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->stage);
        $payment = $booking->schedule->schedulePayment;
        $this->assertTrue($payment->is_manual);
        $this->assertSame(390.0, (float) $payment->paid_amount);
        $this->assertSame('TED 789', $payment->manual_notes);
        $this->assertSame(1, OneOffAccess::where('cpf', '52998224725')->count());
    }

    private function operador(array $permissions): \App\Models\User
    {
        $user = Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(new \App\Authorization\UserAccess($permissions));
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->id = 9;
        $user->name = 'Secretaria';

        return $user;
    }

    public function test_rota_da_baixa_pede_a_permissao_e_volta_para_o_agendamento(): void
    {
        $schedule = $this->courtSchedule(1, createdBy: 9);
        $form = ['schedule_ids' => [$schedule->id], 'payment_method' => 'manual_cash', 'manual_notes' => 'Recibo 1'];

        $this->actingAs($this->operador([\App\Authorization\Permissions::RESERVAS_AGENDAMENTOS]))
            ->post(route('payment.manual'), $form)
            ->assertForbidden();
        $this->assertSame(0, SchedulePayment::count());

        $this->actingAs($this->operador([\App\Authorization\Permissions::RESERVAS_PAGAMENTOS_BAIXA_MANUAL]))
            ->post(route('payment.manual'), $form)
            ->assertRedirect(route('schedule.show', $schedule->id))
            ->assertSessionHas('success', 'Pagamento de R$ 80,00 registrado (Dinheiro).');

        // De novo: já pago, volta com o erro.
        $this->actingAs($this->operador([\App\Authorization\Permissions::RESERVAS_PAGAMENTOS_BAIXA_MANUAL]))
            ->from(route('schedule.show', $schedule->id))
            ->post(route('payment.manual'), $form)
            ->assertSessionHas('error', 'Este agendamento já tem pagamento registrado.');
        $this->assertSame(1, SchedulePayment::count());
    }

    public function test_formulario_do_balcao_fecha_a_reserva_so_com_a_permissao(): void
    {
        $members = Mockery::mock(\App\Services\MemberService::class);
        $members->shouldReceive('memberByCpf')->andReturn($this->ana);
        $this->app->instance(\App\Services\MemberService::class, $members);

        $form = [
            'place_id' => $this->hall->id, 'date' => self::EVENT, 'event_type' => 'Formatura',
            'cpf' => '39053344705', 'complete' => 1, 'paid_now' => 1, 'payment_method' => 'manual_cash',
        ];

        // Quem só pré-reserva não fecha.
        $this->actingAs($this->operador([\App\Authorization\Permissions::RESERVAS_SOCIAIS]))
            ->from(route('social-booking.create'))
            ->post(route('social-booking.store'), $form)
            ->assertSessionHas('error');
        $this->assertSame(0, SocialBooking::count());

        // Quem aprova mas não dá baixa também não registra o pagamento.
        $this->actingAs($this->operador([\App\Authorization\Permissions::RESERVAS_SOCIAIS, \App\Authorization\Permissions::RESERVAS_SOCIAIS_APROVAR]))
            ->from(route('social-booking.create'))
            ->post(route('social-booking.store'), $form)
            ->assertSessionHas('error', 'Registrar o pagamento pede a permissão de baixa manual de pagamento.');

        $response = $this->actingAs($this->operador([
            \App\Authorization\Permissions::RESERVAS_SOCIAIS,
            \App\Authorization\Permissions::RESERVAS_SOCIAIS_APROVAR,
            \App\Authorization\Permissions::RESERVAS_PAGAMENTOS_BAIXA_MANUAL,
        ]))->post(route('social-booking.store'), $form);

        $booking = SocialBooking::firstOrFail();
        $response->assertRedirect(route('social-booking.show', $booking))
            ->assertSessionHas('success', 'Reserva confirmada, com o pagamento registrado.');
        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->stage);
        $this->assertTrue($booking->schedule->schedulePayment->is_manual);
    }

    public function test_balcao_que_falha_em_um_passo_nao_deixa_nada_gravado(): void
    {
        try {
            app(SocialCounterBookingService::class)->book($this->hall, $this->ana, [
                'date' => self::EVENT,
                'event_type' => 'Formatura',
            ], 9, ['discount' => 5000, 'discount_reason' => 'Demais']);
            $this->fail('Esperava a recusa do desconto.');
        } catch (SocialBookingException $e) {
            $this->assertSame(SocialBookingException::INVALID_DISCOUNT, $e->reason);
        }

        $this->assertSame(0, SocialBooking::count());
        $this->assertSame(0, Schedule::withoutGlobalScopes()->count());
    }
}

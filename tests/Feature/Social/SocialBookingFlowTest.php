<?php

namespace Tests\Feature\Social;

use App\Exceptions\SocialBookingException;
use App\Models\Company\OneOffAccess;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Services\ScheduleRulesService;
use App\Services\Social\SocialAvailabilityService;
use App\Services\Social\SocialBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * Reserva de espaço social de ponta a ponta, pelo serviço: pré-reserva (data
 * presa, valor com extras e bebidas), conflito com quadra, limites, contrato,
 * aprovação com desconto e prazo, pagamento, convidados na portaria,
 * expiração e cancelamento.
 *
 * Cenário baseado nos espaços reais do clube (14/10/2026): Churrasqueira da
 * Sauna R$ 800, 20 não sócios incluídos, 5 extras a R$ 15, 80 pessoas; e a
 * "Sauna + Tênis", que conflita com ela.
 */
class SocialBookingFlowTest extends TestCase
{
    use CreatesSocialBookingSchema;

    /** Quarta-feira. */
    private const TODAY = '2026-10-14 10:00:00';

    /** Sábado, 31 dias depois. */
    private const EVENT = '2026-11-14';

    private const CPF_GUEST_1 = '52998224725';
    private const CPF_GUEST_2 = '11144477735';

    private PlaceGroup $group;
    private Place $sauna;
    private Place $saunaTenis;
    private Place $court;
    private SocialBeverage $water;
    private int $memberId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(self::TODAY);
        $this->createSocialBookingSchema();

        $this->group = PlaceGroup::create([
            'name' => 'Churrasqueiras',
            'category' => PlaceGroup::CATEGORY_SOCIAL,
            'minimum_antecedence' => 2,
            'maximum_antecedence' => 180,
            'social_payment_days' => 7,
        ]);
        $this->group->weekdays()->attach(range(1, 7));

        $this->sauna = Place::create([
            'name' => 'Churrasqueira da Sauna', 'place_group_id' => $this->group->id, 'status_id' => 1,
            'price' => 800, 'capacity' => 80, 'max_guests' => 20, 'extra_guests' => 5, 'extra_guest_price' => 15,
        ]);
        $this->saunaTenis = Place::create([
            'name' => 'Churrasqueira da Sauna + Tênis', 'place_group_id' => $this->group->id, 'status_id' => 1,
            'price' => 1200, 'capacity' => 130, 'max_guests' => 40, 'extra_guests' => 5, 'extra_guest_price' => 15,
        ]);

        $sports = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT, 'start_time' => '08:00:00', 'end_time' => '22:00:00', 'duration' => '01:00:00', 'maximum_antecedence' => 60]);
        $sports->weekdays()->attach(range(1, 7));
        $this->court = Place::create(['name' => 'Quadra 1', 'place_group_id' => $sports->id, 'status_id' => 1, 'price' => 80]);

        DB::table('place_conflicts')->insert([
            ['place_id' => $this->saunaTenis->id, 'conflicting_place_id' => $this->sauna->id],
            ['place_id' => $this->sauna->id, 'conflicting_place_id' => $this->court->id],
        ]);

        $this->water = SocialBeverage::create(['name' => 'Água (fardo)', 'unit' => 'fardo 12 un.', 'mv_code' => 'MV-123', 'price' => 30, 'active' => true]);

        $this->memberId = DB::table('members')->insertGetId(['cpf' => '39053344705', 'name' => 'Ana Sócia', 'email' => 'ana@teste.local', 'title' => '1001']);
        DB::table('users')->insert(['id' => 9, 'name' => 'Secretaria']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): SocialBookingService
    {
        return app(SocialBookingService::class);
    }

    private function request(array $overrides = [], ?Place $place = null, ?int $memberId = null): SocialBooking
    {
        return $this->service()->request($place ?? $this->sauna, $memberId ?? $this->memberId, array_merge([
            'date' => self::EVENT,
            'event_type' => 'Aniversário',
            'expected_guests' => 60,
            'extra_guests' => 2,
            'items' => [['beverage_id' => $this->water->id, 'quantity' => 3]],
            'guests' => [['name' => 'Carlos Convidado', 'cpf' => '529.982.247-25']],
        ], $overrides));
    }

    private function assertRefused(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Esperava a recusa {$reason}.");
        } catch (SocialBookingException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
        }
    }

    private function approved(float $discount = 0, ?string $reason = null): SocialBooking
    {
        $booking = $this->request();
        $this->service()->markContract($booking, 9);

        return $this->service()->approve($booking->fresh(), $discount, $reason, 9);
    }

    public function test_pre_reserva_prende_o_dia_inteiro_e_soma_extras_e_bebidas(): void
    {
        $booking = $this->request();

        $this->assertSame(SocialBooking::STAGE_REQUESTED, $booking->stage);
        // 800 + 2 extras × 15 + 3 fardos × 30
        $this->assertSame(800.0, (float) $booking->base_price);
        $this->assertSame(30.0, (float) $booking->extras_total);
        $this->assertSame(90.0, (float) $booking->items_total);
        $this->assertSame(920.0, (float) $booking->total);
        $this->assertSame('MV-123', $booking->items->first()->mv_code);
        $this->assertSame(self::CPF_GUEST_1, $booking->guests->first()->cpf);

        $schedule = $booking->schedule;
        $this->assertSame(3, (int) $schedule->status_id);
        $this->assertSame(self::EVENT . ' 00:00:00', $schedule->start_schedule->format('Y-m-d H:i:s'));
        $this->assertSame(self::EVENT . ' 23:59:59', $schedule->end_schedule->format('Y-m-d H:i:s'));

        $this->assertSame(SocialAvailabilityService::RESERVED, app(SocialAvailabilityService::class)->dayStatus($this->sauna, self::EVENT));
    }

    public function test_a_mesma_data_nao_pode_ser_pedida_duas_vezes(): void
    {
        $this->request();

        $this->assertRefused(SocialBookingException::DATE_UNAVAILABLE, fn () => $this->request());
    }

    public function test_espaco_combinado_conflita_com_cada_parte(): void
    {
        $this->request();

        $this->assertSame(
            SocialAvailabilityService::RESERVED,
            app(SocialAvailabilityService::class)->dayStatus($this->saunaTenis, self::EVENT)
        );
        $this->assertRefused(SocialBookingException::DATE_UNAVAILABLE, fn () => $this->request([], $this->saunaTenis));
    }

    public function test_reserva_social_ocupa_a_quadra_vinculada_e_vice_versa(): void
    {
        $this->request();

        // A grade da quadra no dia do evento: todo horário colide.
        $grid = (new ScheduleRulesService())->getTimeOptions($this->court->id, self::EVENT);
        $this->assertNotEmpty($grid);
        foreach ($grid as $slot) {
            $this->assertNotNull($slot['colides'], "{$slot['start_time']} deveria estar ocupado pelo evento.");
        }

        // E uma reserva de quadra num dia livre bloqueia a churrasqueira.
        Schedule::create(['place_id' => $this->court->id, 'member_id' => $this->memberId, 'start_schedule' => '2026-11-21 10:00:00', 'end_schedule' => '2026-11-21 11:00:00', 'status_id' => 1]);
        $this->assertSame(SocialAvailabilityService::RESERVED, app(SocialAvailabilityService::class)->dayStatus($this->sauna, '2026-11-21'));
    }

    public function test_festa_do_socio_nao_o_impede_de_reservar_quadra_em_outro_espaco(): void
    {
        $other = Place::create(['name' => 'Quadra 2', 'place_group_id' => $this->court->place_group_id, 'status_id' => 1, 'price' => 80]);
        $this->request();

        $grid = (new ScheduleRulesService())->getTimeOptions($other->id, self::EVENT, $this->memberId);

        $this->assertNull($grid[0]['colides']);
    }

    public function test_espaco_social_nao_tem_grade_de_horarios(): void
    {
        $this->assertSame([], (new ScheduleRulesService())->getTimeOptions($this->sauna->id, self::EVENT));
    }

    public function test_janela_de_antecedencia_e_regra_de_exclusao(): void
    {
        $availability = app(SocialAvailabilityService::class);

        $this->assertSame(SocialAvailabilityService::OUT_OF_WINDOW, $availability->dayStatus($this->sauna, '2026-10-15'));
        $this->assertSame(SocialAvailabilityService::OUT_OF_WINDOW, $availability->dayStatus($this->sauna, '2027-06-01'));
        $this->assertSame(SocialAvailabilityService::AVAILABLE, $availability->dayStatus($this->sauna, '2026-10-16'));

        $ruleId = DB::table('schedule_rules')->insertGetId(['name' => 'Manutenção', 'type' => 'exclude', 'start_time' => '12:00', 'end_time' => '13:00', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        DB::table('place_schedule_rule')->insert(['place_id' => $this->sauna->id, 'schedule_rule_id' => $ruleId]);

        $this->assertSame(SocialAvailabilityService::BLOCKED, $availability->dayStatus($this->sauna, self::EVENT));
        $this->assertRefused(SocialBookingException::DATE_BLOCKED, fn () => $this->request());

        $month = collect($availability->month($this->sauna, '2026-11'))->keyBy('date');
        $this->assertCount(30, $month);
        $this->assertSame('blocked', $month[self::EVENT]['status']);
    }

    public function test_convidados_ate_o_incluido_mais_os_extras(): void
    {
        $guests = fn (int $n) => collect(range(1, $n))->map(fn ($i) => ['name' => "Convidado {$i}", 'cpf' => $this->validCpf($i)])->all();

        // 20 incluídos + 2 extras = 22.
        $booking = $this->request(['guests' => $guests(22)]);
        $this->assertCount(22, $booking->guests);

        $this->assertRefused(SocialBookingException::OVER_CAPACITY, fn () => $this->request(['date' => '2026-11-20', 'guests' => $guests(23)]));
        $this->assertRefused(SocialBookingException::OVER_CAPACITY, fn () => $this->request(['date' => '2026-11-20', 'extra_guests' => 6]));
        $this->assertRefused(SocialBookingException::OVER_CAPACITY, fn () => $this->request(['date' => '2026-11-20', 'expected_guests' => 81]));
        $this->assertRefused(SocialBookingException::INVALID_GUEST, fn () => $this->request(['date' => '2026-11-20', 'guests' => [['name' => 'X', 'cpf' => '123']]]));
        $this->assertRefused(SocialBookingException::INVALID_GUEST, fn () => $this->request(['date' => '2026-11-20', 'guests' => [
            ['name' => 'A', 'cpf' => self::CPF_GUEST_1], ['name' => 'B', 'cpf' => self::CPF_GUEST_1],
        ]]));
    }

    public function test_limite_de_reservas_ativas(): void
    {
        $this->group->update(['social_active_limit' => 1]);
        $this->request();

        $this->assertRefused(SocialBookingException::LIMIT_REACHED, fn () => $this->request(['date' => '2026-12-20']));
    }

    public function test_limite_n_a_cada_x_dias(): void
    {
        $this->group->update(['social_limit_count' => 1, 'social_limit_days' => 30]);
        $this->request();

        $this->assertRefused(SocialBookingException::LIMIT_REACHED, fn () => $this->request(['date' => '2026-12-13'])); // 29 dias depois
        $this->assertSame('2026-12-14', $this->request(['date' => '2026-12-14'])->event_date->toDateString()); // 30 dias depois
    }

    public function test_reserva_recusada_nao_conta_no_limite_e_libera_a_data(): void
    {
        $this->group->update(['social_active_limit' => 1]);
        $booking = $this->request();
        $this->service()->refuse($booking, 'Data reservada para evento do clube.', 9);

        $this->assertSame(0, (int) $booking->schedule->fresh()->status_id);
        $this->assertSame(SocialBooking::STAGE_REQUESTED, $this->request()->stage);
    }

    public function test_aprovar_exige_contrato_e_motivo_do_desconto(): void
    {
        $booking = $this->request();

        $this->assertRefused(SocialBookingException::CONTRACT_PENDING, fn () => $this->service()->approve($booking, 0, null, 9));

        $this->service()->markContract($booking, 9);
        $this->assertRefused(SocialBookingException::INVALID_DISCOUNT, fn () => $this->service()->approve($booking->fresh(), 50, '', 9));
        $this->assertRefused(SocialBookingException::INVALID_DISCOUNT, fn () => $this->service()->approve($booking->fresh(), 5000, 'demais', 9));
    }

    public function test_aprovacao_aplica_desconto_e_prazo_de_pagamento(): void
    {
        $booking = $this->approved(120, 'Sócio remido');

        $this->assertSame(SocialBooking::STAGE_AWAITING_PAYMENT, $booking->stage);
        $this->assertSame(800.0, (float) $booking->total);
        $this->assertSame(800.0, (float) $booking->schedule->fresh()->price);
        $this->assertSame('2026-10-21 23:59:59', $booking->payment_due_at->format('Y-m-d H:i:s'));
        Mail::assertQueued(\App\Mail\ContactMail::class, fn ($mail) => $mail->data['type'] === 'social.approved');
    }

    public function test_prazo_nunca_passa_da_vespera_do_evento(): void
    {
        $booking = $this->request(['date' => '2026-10-17']);
        $this->service()->markContract($booking, 9);
        $booking = $this->service()->approve($booking->fresh(), 0, null, 9);

        $this->assertSame('2026-10-16 23:59:59', $booking->payment_due_at->format('Y-m-d H:i:s'));
    }

    public function test_desconto_integral_confirma_sem_cobranca(): void
    {
        $booking = $this->approved(920, 'Cortesia da diretoria');

        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->stage);
        $this->assertSame(1, (int) $booking->schedule->fresh()->status_id);
        $this->assertSame(1, OneOffAccess::count());
    }

    public function test_pagamento_confere_valor_e_estagio(): void
    {
        $pending = $this->request(['date' => '2026-12-20']);
        $this->assertRefused(SocialBookingException::PAYMENT_MISMATCH, fn () => $this->service()->assertPayable(collect([$pending]), [$pending->schedule_id], 920));

        $booking = $this->approved();
        $this->assertRefused(SocialBookingException::PAYMENT_MISMATCH, fn () => $this->service()->assertPayable(collect([$booking]), [$booking->schedule_id], 900));
        $this->assertRefused(SocialBookingException::PAYMENT_MISMATCH, fn () => $this->service()->assertPayable(collect([$booking]), [$booking->schedule_id, 999], 920));

        $this->assertTrue($this->service()->assertPayable(collect([$booking]), [$booking->schedule_id], 920.0)->is($booking));

        Carbon::setTestNow('2026-10-22 00:00:01');
        $this->assertRefused(SocialBookingException::PAYMENT_MISMATCH, fn () => $this->service()->assertPayable(collect([$booking->fresh()]), [$booking->schedule_id], 920));
    }

    public function test_pagamento_confirma_e_libera_os_convidados_na_portaria(): void
    {
        $booking = $this->approved();
        $payment = SchedulePayment::create(['payment_method' => 'credit', 'paid_amount' => 920, 'payment_integration_id' => 'tid-1', 'status_id' => 1]);
        $booking->schedule->update(['schedule_payment_id' => $payment->id, 'status_id' => 1]);

        $this->service()->markPaid($booking, $payment);

        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->fresh()->stage);
        $access = OneOffAccess::first();
        $this->assertSame(self::CPF_GUEST_1, $access->cpf);
        $this->assertSame(self::EVENT, $access->access_date->toDateString());
        $this->assertStringContainsString('reserva social #' . $booking->id, $access->reason);
        $this->assertSame($access->id, $booking->guests()->first()->one_off_access_id);
    }

    public function test_lista_alterada_depois_de_confirmada_sincroniza_a_portaria(): void
    {
        $booking = $this->approved(920, 'Cortesia');
        $first = OneOffAccess::first();

        $this->service()->replaceGuests($booking, [['name' => 'Beatriz', 'cpf' => self::CPF_GUEST_2]], true);

        $this->assertNotNull($first->fresh()->canceled_at);
        $this->assertSame(1, OneOffAccess::whereNull('canceled_at')->where('cpf', self::CPF_GUEST_2)->count());
    }

    public function test_socio_so_mexe_na_lista_ate_a_vespera(): void
    {
        $booking = $this->request();

        Carbon::setTestNow(self::EVENT . ' 08:00:00');
        $this->assertRefused(SocialBookingException::GUESTS_LOCKED, fn () => $this->service()->replaceGuests($booking, [], true));

        // A equipe ainda pode.
        $this->assertCount(0, $this->service()->replaceGuests($booking, [], false, 9)->guests);
    }

    public function test_prazo_vencido_expira_e_libera_a_data(): void
    {
        $booking = $this->approved();

        Carbon::setTestNow('2026-10-22 00:05:00');
        $this->assertSame(1, $this->service()->expireOverdue());

        $this->assertSame(SocialBooking::STAGE_EXPIRED, $booking->fresh()->stage);
        $this->assertSame(4, (int) Schedule::withoutGlobalScopes()->find($booking->schedule_id)->status_id);
        $this->assertSame(SocialAvailabilityService::AVAILABLE, app(SocialAvailabilityService::class)->dayStatus($this->sauna, self::EVENT));
    }

    public function test_hold_de_dez_minutos_das_quadras_nao_expira_a_pre_reserva(): void
    {
        $booking = $this->request();
        $courtPending = Schedule::create(['place_id' => $this->court->id, 'member_id' => $this->memberId, 'start_schedule' => '2026-10-20 10:00:00', 'end_schedule' => '2026-10-20 11:00:00', 'status_id' => 3]);

        Carbon::setTestNow('2026-10-14 11:00:00');
        $this->artisan('app:expire-pending-schedules')->assertSuccessful();

        $this->assertSame(3, (int) $booking->schedule->fresh()->status_id);
        $this->assertSame(4, (int) Schedule::withoutGlobalScopes()->find($courtPending->id)->status_id);
    }

    public function test_cancelamento_pela_equipe_cancela_as_liberacoes(): void
    {
        $booking = $this->approved(920, 'Cortesia');

        $result = $this->service()->cancel($booking, 'Pedido do sócio', 9);

        $this->assertSame(0.0, $result['refunded']);
        $this->assertSame(SocialBooking::STAGE_CANCELLED, $booking->fresh()->stage);
        $this->assertSame(0, (int) $booking->schedule->fresh()->status_id);
        $this->assertNotNull(OneOffAccess::first()->canceled_at);
    }

    public function test_socio_nao_desiste_de_reserva_paga(): void
    {
        $booking = $this->approved(920, 'Cortesia');

        $this->assertRefused(SocialBookingException::INVALID_STAGE, fn () => $this->service()->cancelByMember($booking));

        $unpaid = $this->request(['date' => '2026-12-20']);
        $this->assertSame(SocialBooking::STAGE_CANCELLED, $this->service()->cancelByMember($unpaid)->stage);
    }

    public function test_bebida_inativa_nao_entra_mas_a_ja_contratada_mantem_o_preco(): void
    {
        $booking = $this->request();
        $this->water->update(['price' => 50, 'active' => false]);

        $this->assertRefused(SocialBookingException::BEVERAGE_UNAVAILABLE, fn () => $this->request(['date' => '2026-12-20']));

        $updated = $this->service()->updateDetails($booking, ['event_type' => 'Aniversário', 'extra_guests' => 2, 'items' => [['beverage_id' => $this->water->id, 'quantity' => 4]]]);
        $this->assertSame(120.0, (float) $updated->items_total);
        $this->assertSame(950.0, (float) $updated->total);
    }

    public function test_pre_reserva_e_editavel_ate_o_contrato_ok_menos_data_e_espaco(): void
    {
        $beer = SocialBeverage::create(['name' => 'Refrigerante', 'price' => 12, 'active' => true]);
        $booking = $this->request();

        $edited = $this->service()->updateDetails($booking, [
            'event_type' => 'Formatura',
            'extra_guests' => 5,
            'notes' => 'Bolo às 16h',
            'items' => [['beverage_id' => $beer->id, 'quantity' => 10]], // água saiu, refrigerante entrou
            'guests' => [['name' => 'Beatriz', 'cpf' => self::CPF_GUEST_2]],
            'date' => '2026-12-31', 'place_id' => $this->saunaTenis->id, // ignorados
        ]);

        $this->assertSame('Formatura', $edited->event_type);
        $this->assertSame(['Refrigerante'], $edited->items->pluck('name')->all());
        $this->assertSame(['Beatriz'], $edited->guests->pluck('name')->all());
        // 800 + 5 × 15 + 10 × 12
        $this->assertSame(995.0, (float) $edited->total);
        $this->assertSame(995.0, (float) $edited->schedule->price);
        $this->assertSame(self::EVENT, $edited->event_date->toDateString());
        $this->assertSame($this->sauna->id, $edited->place_id);

        // Campo ausente fica como está.
        $this->assertSame('Bolo às 16h', $this->service()->updateDetails($edited, ['expected_guests' => 30])->notes);

        $this->service()->markContract($edited->fresh(), 9);
        $this->assertRefused(SocialBookingException::CONTRACT_CLOSED, fn () => $this->service()->updateDetails($edited->fresh(), ['event_type' => 'Outro']));
        $this->assertFalse($edited->fresh()->isEditable());
    }

    public function test_reduzir_extras_exige_reduzir_a_lista_junto(): void
    {
        $guests = collect(range(1, 22))->map(fn ($i) => ['name' => "Convidado {$i}", 'cpf' => $this->validCpf($i)])->all();
        $booking = $this->request(['guests' => $guests]); // 20 incluídos + 2 extras

        $this->assertRefused(SocialBookingException::OVER_CAPACITY, fn () => $this->service()->updateDetails($booking, ['extra_guests' => 0]));

        $edited = $this->service()->updateDetails($booking->fresh(), ['extra_guests' => 0, 'guests' => array_slice($guests, 0, 20)]);
        $this->assertCount(20, $edited->guests);
        $this->assertSame(0.0, (float) $edited->extras_total);
    }

    /** CPFs válidos distintos para encher a lista. */
    private function validCpf(int $seed): string
    {
        $base = str_pad((string) (100000000 + $seed * 7919), 9, '0', STR_PAD_LEFT);
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $base[$c] * (($t + 1) - $c);
            }
            $base .= ((10 * $d) % 11) % 10;
        }

        return $base;
    }
}

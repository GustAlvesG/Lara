<?php

namespace Tests\Feature\Social;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Providers\Services\JwtService;
use App\Services\Social\SocialBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * `/api/social/*` pelo app de locação, atravessando as duas autenticações
 * (`api_token` + JWT do sócio), e o pagamento pelo `/api/schedule/payment` de
 * sempre. O que importa: o sócio sai do token (nunca do corpo), reserva alheia
 * é 404, recusa vem com `reason` estável e o pagamento só passa no estágio,
 * no prazo e no valor certos.
 */
class SocialBookingApiTest extends TestCase
{
    use CreatesSocialBookingSchema;

    private string $token = 'token-de-teste';

    private const CPF_ANA = '39053344705';
    private const CPF_BRUNO = '86288366757';
    private const EVENT = '2026-11-14';

    private Place $place;
    private PlaceGroup $group;
    private SocialBeverage $water;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.api.token' => $this->token, 'services.jwt.secret' => 'segredo-de-teste']);
        Carbon::setTestNow('2026-10-14 10:00:00');
        $this->createSocialBookingSchema();

        $this->group = PlaceGroup::create(['name' => 'Salões', 'category' => PlaceGroup::CATEGORY_SOCIAL, 'minimum_antecedence' => 2, 'maximum_antecedence' => 0, 'social_rules' => 'Crianças com menos de 4 anos não contam.']);
        $this->group->weekdays()->attach(range(1, 7));
        $this->place = Place::create([
            'name' => 'Salão Estrela', 'place_group_id' => $this->group->id, 'status_id' => 1, 'price' => 390,
            'capacity' => 70, 'max_guests' => 30, 'extra_guests' => 5, 'extra_guest_price' => 15,
            'amenities' => '10 jogos de mesa com 4 cadeiras, fogão e geladeira.',
        ]);
        PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        $this->water = SocialBeverage::create(['name' => 'Água', 'price' => 30, 'mv_code' => 'MV-9', 'active' => true]);

        DB::table('members')->insert([
            ['id' => 1, 'cpf' => self::CPF_ANA, 'name' => 'Ana', 'email' => 'ana@teste.local', 'title' => '1001'],
            ['id' => 2, 'cpf' => self::CPF_BRUNO, 'name' => 'Bruno', 'email' => 'bruno@teste.local', 'title' => '1002'],
        ]);
        DB::table('users')->insert(['id' => 9, 'name' => 'Secretaria']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function headers(string $cpf = self::CPF_ANA): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            // `exp` no relógio real: o JWT não enxerga o Carbon::setTestNow.
            'Session' => (new JwtService())->generateToken(['username' => $cpf, 'exp' => time() + 3600]),
        ];
    }

    private function book(array $overrides = [], string $cpf = self::CPF_ANA)
    {
        return $this->withHeaders($this->headers($cpf))->postJson('/api/social/bookings', array_merge([
            'place_id' => $this->place->id,
            'date' => self::EVENT,
            'event_type' => 'Aniversário',
            'expected_guests' => 50,
            'extra_guests' => 1,
            'items' => [['beverage_id' => $this->water->id, 'quantity' => 2]],
            'guests' => [['name' => 'Carlos', 'cpf' => '529.982.247-25']],
        ], $overrides));
    }

    public function test_sem_sessao_do_socio_nao_passa(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'])
            ->getJson('/api/social/bookings')
            ->assertStatus(400);
    }

    public function test_grupos_e_espacos_sociais_ficam_fora_da_lista_das_quadras(): void
    {
        $this->withHeaders($this->headers())->getJson('/api/social/groups')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Salões')
            ->assertJsonPath('0.rules', 'Crianças com menos de 4 anos não contam.');

        $this->withHeaders($this->headers())->getJson("/api/social/groups/{$this->group->id}/places")
            ->assertOk()
            ->assertJsonPath('places.0.max_guests', 30)
            ->assertJsonPath('places.0.extra_guest_price', 15)
            ->assertJsonPath('places.0.capacity', 70);

        $names = collect($this->withHeaders($this->headers())->getJson('/api/places/group')->assertOk()->json())->pluck('name');
        $this->assertSame(['Tênis'], $names->values()->all());
    }

    public function test_calendario_do_mes(): void
    {
        $this->book()->assertCreated();

        $days = collect($this->withHeaders($this->headers(self::CPF_BRUNO))
            ->getJson("/api/social/availability?place_id={$this->place->id}&month=2026-11")
            ->assertOk()
            ->assertJsonPath('price', 390)
            ->json('days'))->keyBy('date');

        $this->assertSame('reserved', $days[self::EVENT]['status']);
        $this->assertSame('available', $days['2026-11-15']['status']);
        // A resposta não diz de quem é a data ocupada.
        $this->assertSame(['date', 'status'], array_keys($days[self::EVENT]));
    }

    public function test_pre_reserva_usa_o_socio_do_token_e_ignora_o_do_corpo(): void
    {
        $response = $this->book(['member_id' => 2, 'cpf' => self::CPF_BRUNO])
            ->assertCreated()
            ->assertJsonPath('stage', 'requested')
            ->assertJsonPath('total', 465) // 390 + 15 + 2 × 30
            ->assertJsonPath('guest_limit', 31)
            ->assertJsonPath('guests.0.cpf', '529.***.**7-25')
            ->assertJsonPath('can_pay', false)
            ->assertJsonPath('can_cancel', true);

        $this->assertSame(1, SocialBooking::find($response->json('id'))->member_id);
    }

    public function test_recusa_vem_com_reason_estavel(): void
    {
        $this->book()->assertCreated();

        $this->book([], self::CPF_BRUNO)->assertStatus(422)->assertJsonPath('reason', 'date_unavailable');
        $this->book(['date' => '2026-10-15'], self::CPF_BRUNO)->assertStatus(422)->assertJsonPath('reason', 'out_of_window');
        $this->book(['date' => '2026-11-20', 'extra_guests' => 9], self::CPF_BRUNO)->assertStatus(422)->assertJsonPath('reason', 'over_capacity');
    }

    public function test_reserva_de_outro_socio_e_404(): void
    {
        $id = $this->book()->json('id');

        $this->withHeaders($this->headers(self::CPF_BRUNO))->getJson("/api/social/bookings/{$id}")->assertNotFound();
        $this->withHeaders($this->headers(self::CPF_BRUNO))->deleteJson("/api/social/bookings/{$id}")->assertNotFound();
        $this->withHeaders($this->headers())->getJson("/api/social/bookings/{$id}")->assertOk();
    }

    public function test_socio_troca_a_lista_e_desiste(): void
    {
        $id = $this->book()->json('id');

        $this->withHeaders($this->headers())->putJson("/api/social/bookings/{$id}/guests", ['guests' => [
            ['name' => 'Beatriz', 'cpf' => '11144477735'],
        ]])->assertOk()->assertJsonCount(1, 'guests')->assertJsonPath('guests.0.name', 'Beatriz');

        $this->withHeaders($this->headers())->deleteJson("/api/social/bookings/{$id}")
            ->assertOk()
            ->assertJsonPath('stage', 'cancelled');
    }

    public function test_socio_edita_a_pre_reserva_pelo_app_ate_o_contrato_ok(): void
    {
        $id = $this->book()->assertJsonPath('can_edit', true)->json('id');

        $this->withHeaders($this->headers())->putJson("/api/social/bookings/{$id}", [
            'event_type' => 'Chá de bebê',
            'extra_guests' => 0,
            'items' => [],
            'guests' => [],
            'date' => '2026-12-25',
        ])->assertOk()
            ->assertJsonPath('event_type', 'Chá de bebê')
            ->assertJsonPath('date', self::EVENT)
            ->assertJsonPath('total', 390)
            ->assertJsonCount(0, 'items')
            ->assertJsonCount(0, 'guests');

        $this->withHeaders($this->headers(self::CPF_BRUNO))->putJson("/api/social/bookings/{$id}", ['notes' => 'x'])->assertNotFound();

        app(SocialBookingService::class)->markContract(SocialBooking::find($id), 9);

        $this->withHeaders($this->headers())->getJson("/api/social/bookings/{$id}")->assertJsonPath('can_edit', false);
        $this->withHeaders($this->headers())->putJson("/api/social/bookings/{$id}", ['notes' => 'x'])
            ->assertStatus(409)->assertJsonPath('reason', 'contract_closed');
    }

    public function test_mobiliario_e_horario_pela_api(): void
    {
        $this->place->update(['base_tables' => 10, 'base_chairs' => 40, 'event_duration_hours' => 5, 'event_earliest_start' => '09:00', 'event_latest_start' => '19:00']);
        $set = SocialBeverage::create(['name' => 'Jogo completo', 'category' => 'mobiliario', 'price' => 25, 'tables_per_unit' => 1, 'chairs_per_unit' => 4, 'max_quantity' => 3, 'active' => true]);

        // Bebidas e mobiliário em listas separadas; /beverages continua só com bebida.
        $this->withHeaders($this->headers())->getJson('/api/social/beverages')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Água');
        $this->withHeaders($this->headers())->getJson('/api/social/furniture')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.chairs_per_unit', 4)->assertJsonPath('0.max_quantity', 3);

        $this->withHeaders($this->headers())->getJson("/api/social/groups/{$this->group->id}/places")->assertOk()
            ->assertJsonPath('places.0.base_tables', 10)
            ->assertJsonPath('places.0.event_duration_hours', 5)
            ->assertJsonPath('places.0.event_earliest_start', '09:00');

        $this->book(['items' => [['item_id' => $set->id, 'quantity' => 1]]])->assertStatus(422)->assertJsonPath('reason', 'invalid_time');
        $this->book(['start_time' => '08:00'])->assertStatus(422)->assertJsonPath('reason', 'invalid_time');
        $this->book(['start_time' => '14:00', 'items' => [['item_id' => $set->id, 'quantity' => 4]]])->assertStatus(422)->assertJsonPath('reason', 'item_limit');

        $id = $this->book(['start_time' => '14:00', 'items' => [['item_id' => $set->id, 'quantity' => 2], ['beverage_id' => $this->water->id, 'quantity' => 1]]])
            ->assertCreated()
            ->assertJsonPath('start_time', '14:00')
            ->assertJsonPath('end_time', '19:00')
            ->assertJsonPath('ends_next_day', false)
            ->assertJsonPath('tables_total', 12)
            ->assertJsonPath('chairs_total', 48)
            ->assertJsonPath('total', 485) // 390 + 15 + 2 × 25 + 30
            ->json('id');

        $this->withHeaders($this->headers())->putJson("/api/social/bookings/{$id}", ['start_time' => '18:00'])
            ->assertOk()->assertJsonPath('end_time', '23:00');
    }

    public function test_pagamento_pelo_endpoint_de_sempre(): void
    {
        $booking = SocialBooking::find($this->book()->json('id'));
        $service = app(SocialBookingService::class);
        $service->markContract($booking, 9);
        $booking = $service->approve($booking->fresh(), 15, 'Ajuste', 9);

        $pay = fn (float $amount) => $this->withHeaders($this->headers())->postJson('/api/schedule/payment', [
            'schedule_ids' => [$booking->schedule_id],
            'status_id' => 1,
            'payment_method' => 'credit',
            'paid_amount' => $amount,
            'payment_integration_id' => 'tid-123',
            'paid_at' => '2026-10-14T10:30:00-03:00',
        ]);

        $pay(465)->assertStatus(422)->assertJsonPath('reason', 'payment_mismatch');
        $pay(450)->assertCreated();

        $booking->refresh();
        $this->assertSame(SocialBooking::STAGE_CONFIRMED, $booking->stage);
        $this->assertSame(1, (int) $booking->schedule->status_id);
        $this->assertSame(1, DB::table('one_off_accesses')->whereDate('access_date', self::EVENT)->count());
        Mail::assertQueued(\App\Mail\ContactMail::class, fn ($mail) => $mail->data['type'] === 'social.confirmed');
        Mail::assertNotQueued(\App\Mail\ContactMail::class, fn ($mail) => $mail->data['type'] === 'schedule.confirm');

        // Depois de pago, o sócio não desiste pelo app.
        $this->withHeaders($this->headers())->deleteJson("/api/social/bookings/{$booking->id}")
            ->assertStatus(409)->assertJsonPath('reason', 'invalid_stage');
    }

    public function test_reserva_fechada_no_balcao_vai_para_o_perfil_do_socio_pagar(): void
    {
        $booking = app(\App\Services\Social\SocialCounterBookingService::class)->book($this->place, 1, [
            'date' => self::EVENT,
            'event_type' => 'Formatura',
        ], 9, ['discount' => 40, 'discount_reason' => 'Cortesia']);

        $this->withHeaders($this->headers())->getJson('/api/social/bookings')
            ->assertOk()
            ->assertJsonPath('0.id', $booking->id)
            ->assertJsonPath('0.origin', 'secretaria')
            ->assertJsonPath('0.stage', 'awaiting_payment')
            ->assertJsonPath('0.total', 350)
            ->assertJsonPath('0.can_pay', true)
            ->assertJsonPath('0.payment', null);
        Mail::assertQueued(\App\Mail\ContactMail::class, fn ($mail) => $mail->data['type'] === 'social.approved');
        Mail::assertNotQueued(\App\Mail\ContactMail::class, fn ($mail) => $mail->data['type'] === 'social.requested');

        // O sócio pagou na secretaria: baixa manual, e o site para de cobrar.
        app(\App\Services\ManualPaymentService::class)->record([$booking->schedule_id], ['payment_method' => 'manual_pix'], 9);

        $this->withHeaders($this->headers())->getJson("/api/social/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('stage', 'confirmed')
            ->assertJsonPath('can_pay', false)
            ->assertJsonPath('payment.manual', true)
            ->assertJsonPath('payment.method', 'manual_pix')
            ->assertJsonPath('payment.method_label', 'Pix direto ao clube')
            ->assertJsonPath('payment.paid_amount', 350);

        $this->withHeaders($this->headers())->postJson('/api/schedule/payment', [
            'schedule_ids' => [$booking->schedule_id],
            'status_id' => 1,
            'payment_method' => 'credit',
            'paid_amount' => 350,
            'payment_integration_id' => 'tid-999',
            'paid_at' => '2026-10-14T10:30:00-03:00',
        ])->assertStatus(409);
    }

    public function test_reserva_do_proprio_site_tem_origem_site(): void
    {
        $this->book()->assertCreated()->assertJsonPath('origin', 'site')->assertJsonPath('payment', null);
    }

    public function test_espaco_social_nao_entra_pela_reserva_de_quadra(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/schedule', [
            'place_id' => $this->place->id,
            'date' => self::EVENT,
            'selected_slots' => ['00:00 - 23:59'],
        ])->assertStatus(500)->assertJsonPath('error', 'Espaço social é reservado pela tela de Espaços Sociais.');

        $this->assertSame(0, DB::table('schedules')->count());
    }
}

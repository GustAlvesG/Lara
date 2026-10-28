<?php

namespace Tests\Feature\Tickets;

use App\Authorization\Permissions as P;
use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketSeat;
use App\Models\Ticket\TicketSeatClaim;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Providers\Services\JwtService;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketCheckinService;
use App\Services\Ticket\TicketInventory;
use App\Services\Ticket\TicketOrderService;
use App\Services\Ticket\TicketSeatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Lugar marcado (F13): mapas com mesas, bistrôs e camarotes. O lugar é
 * escolhido no mapa e não é vendido duas vezes no mesmo dia (trava do evento
 * + índice único de ticket_seat_claims); o ingresso do lugar não é entrada
 * (fora do "um por CPF", da lotação e da portaria); o pendente vencido solta
 * o lugar; o pagamento tardio com o lugar tomado volta `hold_expired`.
 */
class TicketSeatMapTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';

    private string $token = 'token-de-teste';
    private User $staff;
    private TicketEvent $event;
    private TicketType $pista;
    private TicketType $mesa;
    private TicketMap $map;
    /** @var array<string, TicketSeat> */
    private array $seats = [];
    private TicketOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => $this->token, 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Secretaria');
        $this->joinSector($this->staff, 'TI');

        $this->event = $this->ticketEvent(['capacity' => 2]);
        $this->pista = $this->ticketType($this->event, 'Pista');
        $this->ticketLot($this->pista);

        $this->map = $this->event->maps()->create(['name' => 'Mesas', 'areas' => [
            ['label' => 'Palco', 'shape' => 'rect', 'x' => 400, 'y' => 10, 'w' => 200, 'h' => 60, 'color' => 'stage'],
        ]]);
        foreach (['M1' => 'Comum', 'M2' => 'Comum', 'V1' => 'Vip'] as $label => $category) {
            $this->seats[$label] = $this->map->seats()->create(['label' => $label, 'category' => $category, 'x' => 100 + count($this->seats) * 60, 'y' => 200, 'places' => 4]);
        }
        $this->mesa = $this->ticketType($this->event, 'Mesa', ['ticket_map_id' => $this->map->id, 'seat_category' => 'Comum']);
        $this->ticketLot($this->mesa, ['price_member' => 200, 'price_non_member' => 300]);

        $this->orders = app(TicketOrderService::class);
    }

    private function guest(int $index = 0): TicketBuyer
    {
        return TicketBuyer::guest(self::GUEST_CPFS[$index], 'Comprador ' . $index, "c{$index}@x.local", null);
    }

    private function seatItem(TicketType $type, TicketSeat $seat, int $holder = 0): array
    {
        return $this->holder($type->id, $holder) + ['seat_id' => $seat->id];
    }

    private function appHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    public function test_o_lugar_nao_e_vendido_duas_vezes(): void
    {
        $first = $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $ticket = $first->tickets->sole();

        $this->assertSame($this->seats['M1']->id, $ticket->ticket_seat_id);
        $this->assertSame(1, TicketSeatClaim::where('ticket_seat_id', $this->seats['M1']->id)->where('day_key', 0)->count());

        try {
            $this->orders->create($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M1'], 1)]);
            $this->fail('Esperava seat_taken.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::SEAT_TAKEN, $e->reason);
            $this->assertSame([$this->seats['M1']->id], $e->context['seat_ids']);
        }

        // Nem o balcão, com força, vende o mesmo lugar.
        $this->expectException(TicketException::class);
        $this->orders->issueAtCounter($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M1'], 1)], $this->staff, TicketOrderService::ISSUE_COURTESY, force: true);
    }

    public function test_o_indice_unico_segura_quem_escapar_da_trava(): void
    {
        $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $other = $this->orders->create($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M2'], 1)])->tickets->sole();
        TicketSeatClaim::where('ticket_id', $other->id)->delete();

        // Gravação direta do mesmo lugar e dia, sem passar pelo serviço: o banco recusa.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        TicketSeatClaim::create(['ticket_seat_id' => $this->seats['M1']->id, 'ticket_id' => $other->id, 'day_key' => 0]);
    }

    public function test_lugar_obrigatorio_do_tipo_e_da_categoria(): void
    {
        $cases = [
            [$this->holder($this->mesa->id, 0), TicketException::SEAT_REQUIRED],
            [$this->holder($this->mesa->id, 0) + ['seat_id' => 99999], TicketException::SEAT_NOT_FOUND],
            // V1 é da categoria Vip: a Mesa (Comum) não vende.
            [$this->seatItem($this->mesa, $this->seats['V1']), TicketException::SEAT_WRONG_TYPE],
        ];

        foreach ($cases as [$item, $reason]) {
            try {
                $this->orders->create($this->event, $this->guest(0), [$item]);
                $this->fail("Esperava {$reason}.");
            } catch (TicketException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }

        $this->seats['M2']->update(['active' => false]);
        try {
            $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M2'])]);
            $this->fail('Esperava seat_not_found.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::SEAT_NOT_FOUND, $e->reason);
        }
    }

    public function test_mesa_fica_fora_do_um_por_cpf_e_da_lotacao(): void
    {
        // Lotação 2: duas entradas + duas mesas cabem; a mesma pessoa tem a entrada e a mesa.
        $order = $this->orders->create($this->event, $this->guest(0), [
            $this->holder($this->pista->id, 0),
            $this->holder($this->pista->id, 1),
            $this->seatItem($this->mesa, $this->seats['M1'], 0),
            $this->seatItem($this->mesa, $this->seats['M2'], 0),
        ]);

        $this->assertCount(4, $order->tickets);
        $this->assertSame(0, app(TicketInventory::class)->capacityLeft($this->event->fresh()));
        // Não sócio: 2 × R$ 80 (pista) + 2 × R$ 300 (mesa).
        $this->assertSame(760.0, (float) $order->total);
    }

    public function test_pendente_vencido_solta_o_lugar_e_o_pagamento_tardio_volta_hold_expired(): void
    {
        $late = $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);

        $this->travel(11)->minutes();

        // Ainda sem o comando de expirar: o lugar já está livre para outra pessoa.
        $this->orders->create($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M1'], 1)]);

        try {
            $this->orders->pay($late, ['payment_method' => 'credit', 'paid_amount' => 300, 'payment_integration_id' => 'TID-1']);
            $this->fail('Esperava hold_expired.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::HOLD_EXPIRED, $e->reason);
            $this->assertSame([$this->seats['M1']->id], $e->context['seat_ids']);
        }

        // A baixa manual, nem confirmando, vende o lugar de novo.
        try {
            $this->orders->recordManual($late, ['payment_method' => 'manual_pix'], $this->staff, force: true);
            $this->fail('Esperava hold_expired.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::HOLD_EXPIRED, $e->reason);
        }
    }

    public function test_pagamento_tardio_com_o_lugar_livre_prende_de_novo(): void
    {
        $order = $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $this->travel(11)->minutes();
        $this->assertSame(1, $this->orders->expireOverdue());
        $this->assertSame(0, TicketSeatClaim::count());

        $this->orders->pay($order->fresh(), ['payment_method' => 'credit', 'paid_amount' => 300, 'payment_integration_id' => 'TID-2']);

        $this->assertSame(Ticket::STATUS_VALID, $order->tickets()->sole()->status);
        $this->assertSame(1, TicketSeatClaim::where('ticket_seat_id', $this->seats['M1']->id)->count());
    }

    public function test_desistir_e_cancelar_soltam_o_lugar(): void
    {
        $pending = $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $this->orders->withdraw($pending);
        $this->assertSame(0, TicketSeatClaim::count());

        $paid = $this->orders->issueAtCounter($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M1'], 1)], $this->staff, TicketOrderService::ISSUE_COURTESY);
        $this->assertSame(1, TicketSeatClaim::count());

        $this->orders->cancelTickets($paid, null, $this->staff, 'Desistiu');
        $this->assertSame(0, TicketSeatClaim::count());

        $this->orders->create($this->event, $this->guest(2), [$this->seatItem($this->mesa, $this->seats['M1'], 2)]);
        $this->assertSame(1, TicketSeatClaim::count());
    }

    public function test_mesma_mesa_em_dias_diferentes(): void
    {
        $event = $this->ticketEvent(['title' => 'Festival', 'ends_at' => now()->addDays(22)->setTime(4, 0)]);
        $sat = $event->days()->create(['name' => 'Sábado', 'starts_at' => $event->starts_at, 'ends_at' => $event->starts_at->copy()->addHours(5)]);
        $sun = $event->days()->create(['name' => 'Domingo', 'starts_at' => $event->starts_at->copy()->addDay(), 'ends_at' => $event->starts_at->copy()->addDay()->addHours(5)]);
        $map = $event->maps()->create(['name' => 'Mesas']);
        $seat = $map->seats()->create(['label' => 'M1', 'x' => 100, 'y' => 100]);
        $mesaSat = $this->ticketType($event, 'Mesa', ['ticket_map_id' => $map->id, 'ticket_event_day_id' => $sat->id]);
        $mesaSun = $this->ticketType($event, 'Mesa', ['ticket_map_id' => $map->id, 'ticket_event_day_id' => $sun->id]);
        $this->ticketLot($mesaSat);
        $this->ticketLot($mesaSun);

        $this->orders->create($event, $this->guest(0), [$this->seatItem($mesaSat, $seat), $this->seatItem($mesaSun, $seat)]);
        $this->assertSame([$sat->id, $sun->id], TicketSeatClaim::orderBy('day_key')->pluck('day_key')->all());

        $this->expectException(TicketException::class);
        $this->orders->create($event, $this->guest(1), [$this->seatItem($mesaSun, $seat, 1)]);
    }

    public function test_regras_do_tipo_de_lugar(): void
    {
        $event = $this->ticketEvent(['title' => 'Dois dias', 'ends_at' => now()->addDays(22)->setTime(4, 0)]);
        $day = $event->days()->create(['starts_at' => $event->starts_at, 'ends_at' => $event->starts_at->copy()->addHours(5)]);
        $map = $event->maps()->create(['name' => 'Mesas']);

        // Lugar é por dia: sem passaporte de mesa.
        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Mesa', 'ticket_map_id' => $map->id])
            ->assertSessionHasErrors('ticket_map_id');

        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Mesa', 'ticket_map_id' => $map->id, 'ticket_event_day_id' => $day->id])
            ->assertRedirect();

        // Outro tipo no mesmo mapa e dia, cruzando a categoria (vazia = todas): recusa.
        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Mesa Vip', 'ticket_map_id' => $map->id, 'ticket_event_day_id' => $day->id, 'seat_category' => 'Vip'])
            ->assertSessionHasErrors('seat_category');

        // Mapa de outro evento: recusa.
        $this->actingAs($this->staff)->post(route('tickets.types.store', $this->event), ['name' => 'Mesa 2', 'ticket_map_id' => $map->id])
            ->assertSessionHasErrors('ticket_map_id');

        // Vendido, o tipo não muda de mapa nem de categoria.
        $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $this->actingAs($this->staff)->put(route('tickets.types.update', [$this->event, $this->mesa]), ['name' => 'Mesa', 'ticket_map_id' => $this->map->id, 'seat_category' => 'Vip', 'active' => 1])
            ->assertSessionHasErrors('ticket_map_id');
    }

    public function test_desenho_trava_o_lugar_vendido_e_copia_o_mapa(): void
    {
        $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $layout = fn (array $seats) => ['layout' => json_encode(['seats' => $seats, 'areas' => [['label' => 'Piscina', 'shape' => 'ellipse', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 50, 'color' => 'water']]])];
        $row = fn (TicketSeat $s, array $over = []) => array_merge($s->only(['id', 'label', 'category', 'shape', 'x', 'y', 'w', 'h', 'places', 'active']), $over);
        $url = route('tickets.maps.layout', [$this->event, $this->map]);

        // Apagar o vendido (M1 fora da lista): recusa.
        $this->actingAs($this->staff)->put($url, $layout([$row($this->seats['M2']), $row($this->seats['V1'])]))->assertSessionHasErrors('layout');
        // Renomear o vendido: recusa.
        $this->actingAs($this->staff)->put($url, $layout([$row($this->seats['M1'], ['label' => 'M9']), $row($this->seats['M2']), $row($this->seats['V1'])]))->assertSessionHasErrors('layout');
        // Rótulo repetido: recusa.
        $this->actingAs($this->staff)->put($url, $layout([$row($this->seats['M1']), $row($this->seats['M2']), $row($this->seats['V1']), ['label' => 'm2', 'x' => 10, 'y' => 10]]))->assertSessionHasErrors('layout');

        // Mover o vendido, trocar rótulos entre M2 e V1, apagar nada e criar um novo: passa.
        $this->actingAs($this->staff)->put($url, $layout([
            $row($this->seats['M1'], ['x' => 500, 'active' => false]),
            $row($this->seats['M2'], ['label' => 'V1', 'category' => 'Vip']),
            $row($this->seats['V1'], ['label' => 'M2', 'category' => 'Comum']),
            ['label' => 'M3', 'category' => 'Comum', 'shape' => 'square', 'x' => 9999, 'y' => 300, 'w' => 50, 'h' => 50, 'places' => 6],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(500, $this->seats['M1']->fresh()->x);
        $this->assertFalse($this->seats['M1']->fresh()->active);
        $this->assertSame('V1', $this->seats['M2']->fresh()->label);
        $this->assertSame($this->map->width, TicketSeat::where('label', 'M3')->value('x'));
        $this->assertSame('Piscina', $this->map->fresh()->areas[0]['label']);

        // Copiar para outro evento: lugares e áreas, sem ingresso.
        $other = $this->ticketEvent(['title' => 'Outro baile']);
        $this->actingAs($this->staff)->post(route('tickets.maps.store', $other), ['name' => 'Mesas', 'copy_from' => $this->map->id])->assertRedirect();
        $copy = $other->maps()->sole();
        $this->assertSame(4, $copy->seats()->count());
        $this->assertSame('Piscina', $copy->areas[0]['label']);

        // Mapa com tipo não é apagado.
        $this->actingAs($this->staff)->delete(route('tickets.maps.destroy', [$this->event, $this->map]))->assertSessionHasErrors('map');
        $this->actingAs($this->staff)->delete(route('tickets.maps.destroy', [$other, $copy]))->assertRedirect();
        $this->assertSame(0, $other->maps()->count());
    }

    public function test_ingresso_de_mesa_nao_e_entrada(): void
    {
        $this->event->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(6), 'sales_ends_at' => now()->addMinutes(50)]);
        $order = $this->orders->issueAtCounter($this->event, $this->guest(0), [$this->holder($this->pista->id, 0), $this->seatItem($this->mesa, $this->seats['M1'])], $this->staff, TicketOrderService::ISSUE_COURTESY);
        $seatTicket = $order->tickets->firstWhere('ticket_seat_id', $this->seats['M1']->id);
        $checkin = app(TicketCheckinService::class);

        $state = $checkin->evaluate($seatTicket);
        $this->assertFalse($state['allowed']);
        $this->assertSame(TicketCheckinService::REASON_SEAT, $state['reason']);
        $this->assertStringContainsString('M1', $state['message']);

        try {
            $checkin->checkIn($seatTicket, $this->staff, Ticket::CHECKIN_LARA, force: true);
            $this->fail('Esperava seat_not_entry.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::SEAT_NOT_ENTRY, $e->reason);
        }

        // Pelo CPF aparece só a entrada; o contador do check-in também não conta a mesa.
        $this->assertSame([$this->pista->id], $checkin->find(self::GUEST_CPFS[0], $this->event)->pluck('ticket_type_id')->all());
        $this->assertSame(1, $checkin->counts($this->event)['sold']);
    }

    public function test_sem_lugar_livre_o_tipo_esgota(): void
    {
        $this->orders->create($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])]);
        $row = collect(app(TicketInventory::class)->summary($this->event->fresh()))->firstWhere('type.id', $this->mesa->id);
        $this->assertSame(1, $row['seats_free']);
        $this->assertSame(1, $row['remaining']);

        $this->orders->create($this->event, $this->guest(1), [$this->seatItem($this->mesa, $this->seats['M2'], 1)]);
        $row = collect(app(TicketInventory::class)->summary($this->event->fresh()))->firstWhere('type.id', $this->mesa->id);
        $this->assertSame(0, $row['seats_free']);
        $this->assertNull($row['lot']);
    }

    public function test_limite_de_lugares_por_pedido_vale_para_o_socio(): void
    {
        $this->event->update(['max_per_order' => 1]);
        $member = $this->member(self::CPF_ANA, 'Ana Souza');
        $buyer = $this->orders->buyerFor($member);

        try {
            $this->orders->create($this->event->fresh(), $buyer, [['type_id' => $this->mesa->id, 'seat_id' => $this->seats['M1']->id], ['type_id' => $this->mesa->id, 'seat_id' => $this->seats['M2']->id]]);
            $this->fail('Esperava limit_per_order.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::LIMIT_PER_ORDER, $e->reason);
        }

        $order = $this->orders->create($this->event->fresh(), $buyer, [['type_id' => $this->mesa->id, 'seat_id' => $this->seats['M1']->id]]);
        $this->assertSame(self::CPF_ANA, $order->tickets->sole()->holder_cpf);
        $this->assertSame(200.0, (float) $order->total);
    }

    public function test_api_do_site_mostra_o_mapa_e_vende_o_lugar(): void
    {
        $this->orders->create($this->event, $this->guest(3), [$this->seatItem($this->mesa, $this->seats['M2'], 3)]);

        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$this->event->id}")
            ->assertOk()
            ->assertJsonPath('has_map', true)
            ->assertJsonPath('maps.0.name', 'Mesas')
            ->assertJsonPath('types.1.kind', 'seat')
            ->assertJsonPath('types.1.map_id', $this->map->id)
            ->assertJsonPath('types.1.seats_free', 1)
            ->assertJsonPath('types.0.kind', 'entry');

        $map = $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$this->event->id}/maps/{$this->map->id}")
            ->assertOk()
            ->assertJsonPath('areas.0.label', 'Palco')
            ->assertJsonPath('refresh_after', TicketSeatService::REFRESH_SECONDS)
            ->assertJsonPath('types.0.id', $this->mesa->id)
            ->json();
        $byLabel = collect($map['seats'])->keyBy('label');
        $this->assertSame('free', $byLabel['M1']['status']);
        $this->assertSame('held', $byLabel['M2']['status']);
        // Nenhum tipo vende a categoria Vip.
        $this->assertSame('unavailable', $byLabel['V1']['status']);
        $this->assertArrayNotHasKey('holder_name', $byLabel['M2']);

        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$this->event->id}/maps/99999")
            ->assertNotFound()->assertJsonPath('reason', TicketException::MAP_NOT_FOUND);

        $buy = fn (TicketSeat $seat) => $this->withHeaders($this->appHeaders())->postJson("/api/tickets/events/{$this->event->id}/guest-orders", [
            'buyer' => ['cpf' => self::GUEST_CPFS[0], 'name' => 'Carla Visitante', 'email' => 'carla@visita.local'],
            'tickets' => [$this->holder($this->pista->id, 0), $this->seatItem($this->mesa, $seat, 0)],
        ]);

        $buy($this->seats['M2'])->assertStatus(409)->assertJsonPath('reason', TicketException::SEAT_TAKEN)->assertJsonPath('context.seat_ids.0', $this->seats['M2']->id);
        $buy($this->seats['M1'])->assertCreated()
            ->assertJsonPath('tickets.1.kind', 'seat')
            ->assertJsonPath('tickets.1.seat.label', 'M1')
            ->assertJsonPath('tickets.1.seat.map.name', 'Mesas')
            ->assertJsonPath('tickets.0.seat', null);
    }

    public function test_api_do_mapa_pede_o_dia_no_evento_de_varios_dias(): void
    {
        $day = $this->event->days()->create(['starts_at' => $this->event->starts_at, 'ends_at' => $this->event->starts_at->copy()->addHours(5)]);
        $this->mesa->update(['ticket_event_day_id' => $day->id]);

        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$this->event->id}/maps/{$this->map->id}")
            ->assertStatus(422)->assertJsonPath('reason', TicketException::DAY_REQUIRED);

        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$this->event->id}/maps/{$this->map->id}?day_id={$day->id}")
            ->assertOk()->assertJsonPath('day.id', $day->id)->assertJsonPath('types.0.id', $this->mesa->id);
    }

    public function test_socio_compra_a_mesa_pelo_site(): void
    {
        $this->member(self::CPF_ANA, 'Ana Souza');
        $headers = $this->appHeaders() + ['Session' => (new JwtService())->generateToken(['username' => self::CPF_ANA, 'exp' => time() + 3600])];

        $this->withHeaders($headers)->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->mesa->id]]])
            ->assertStatus(422)->assertJsonPath('reason', TicketException::SEAT_REQUIRED);

        $this->withHeaders($headers)->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->pista->id], ['type_id' => $this->mesa->id, 'seat_id' => $this->seats['M1']->id]]])
            ->assertCreated()->assertJsonPath('tickets.1.seat.label', 'M1')->assertJsonPath('total', 250);
    }

    public function test_telas_do_painel(): void
    {
        $order = $this->orders->issueAtCounter($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M1'])], $this->staff, TicketOrderService::ISSUE_COURTESY);

        $this->actingAs($this->staff)->get(route('tickets.show', $this->event))->assertOk()->assertSee('Lugar marcado · Mesas · Comum')->assertSee('Mapas');
        $this->actingAs($this->staff)->get(route('tickets.maps.index', $this->event))->assertOk()->assertSee('Mesas')->assertSee('Vendido por: Mesa (categoria Comum)', false);
        $this->actingAs($this->staff)->get(route('tickets.maps.edit', [$this->event, $this->map]))->assertOk()->assertSee('Salvar desenho')->assertSee('"locked":true', false);
        $this->actingAs($this->staff)->get(route('tickets.maps.occupancy', [$this->event, $this->map]))->assertOk();
        $this->actingAs($this->staff)->getJson(route('tickets.maps.state', [$this->event, $this->map]))
            ->assertOk()->assertJsonPath('seats.0.status', 'sold')->assertJsonPath('seats.0.holder_name', 'Convidado 1')->assertJsonPath('seats.0.order_id', $order->id);
        $this->actingAs($this->staff)->get(route('tickets.issue.create', $this->event))->assertOk()->assertSee('Escolher no mapa')->assertSee('seat-picker', false);
        $this->actingAs($this->staff)->get(route('tickets.orders.show', $order))->assertOk()->assertSee('Lugar M1 (4 lugares)')->assertSee('Trocar lugar');

        // Emissão pelo painel com o lugar.
        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => 'guest',
            'buyer_cpf' => self::GUEST_CPFS[1],
            'buyer_name' => 'Bruno Convidado',
            'holders' => [$this->seatItem($this->mesa, $this->seats['M1'], 1)],
        ])->assertSessionHasErrors('order');

        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => 'guest',
            'buyer_cpf' => self::GUEST_CPFS[1],
            'buyer_name' => 'Bruno Convidado',
            'holders' => [$this->seatItem($this->mesa, $this->seats['M2'], 1)],
        ])->assertRedirect();
        $this->assertSame(2, TicketSeatClaim::count());

        // Troca de lugar: M2 está ocupado; V1 é de outra categoria; sem lugar novo livre, recusa.
        $ticket = $order->tickets->sole();
        $this->actingAs($this->staff)->put(route('tickets.orders.seat', $ticket), ['seat_id' => $this->seats['M2']->id])->assertSessionHasErrors('seat_id');
        $this->actingAs($this->staff)->put(route('tickets.orders.seat', $ticket), ['seat_id' => $this->seats['V1']->id])->assertSessionHasErrors('seat_id');

        $m3 = $this->map->seats()->create(['label' => 'M3', 'category' => 'Comum', 'x' => 400, 'y' => 300]);
        $this->actingAs($this->staff)->put(route('tickets.orders.seat', $ticket), ['seat_id' => $m3->id])->assertRedirect();
        $this->assertSame($m3->id, $ticket->fresh()->ticket_seat_id);
        $this->assertSame([$this->seats['M2']->id, $m3->id], TicketSeatClaim::orderBy('ticket_seat_id')->pluck('ticket_seat_id')->all());
    }

    public function test_quem_so_vende_ve_a_ocupacao_mas_nao_desenha(): void
    {
        $vendas = $this->makeUser('Vendas');
        DB::table('model_has_permissions')->insert([
            'permission_id' => DB::table('permissions')->where('name', P::INGRESSOS_VENDAS)->value('id'),
            'model_type' => 'App\\Models\\User',
            'model_id' => $vendas->id,
        ]);
        $vendas->forgetAccess();

        $this->actingAs($vendas)->get(route('tickets.maps.occupancy', [$this->event, $this->map]))->assertOk();
        $this->actingAs($vendas)->get(route('tickets.maps.edit', [$this->event, $this->map]))->assertForbidden();
        $this->actingAs($vendas)->get(route('tickets.maps.index', $this->event))->assertForbidden();

        // Mapa de outro evento pela rota deste: 404.
        $other = $this->ticketEvent(['title' => 'Outro']);
        $this->actingAs($vendas)->get(route('tickets.maps.occupancy', [$other, $this->map]))->assertNotFound();
    }

    public function test_balcao_avisa_lugar_desativado(): void
    {
        $this->seats['M2']->update(['active' => false]);

        try {
            $this->orders->issueAtCounter($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M2'])], $this->staff, TicketOrderService::ISSUE_COURTESY);
            $this->fail('Esperava confirmação.');
        } catch (ConfirmationRequired) {
        }

        $order = $this->orders->issueAtCounter($this->event, $this->guest(0), [$this->seatItem($this->mesa, $this->seats['M2'])], $this->staff, TicketOrderService::ISSUE_COURTESY, force: true);
        $this->assertSame($this->seats['M2']->id, $order->tickets->sole()->ticket_seat_id);
    }
}

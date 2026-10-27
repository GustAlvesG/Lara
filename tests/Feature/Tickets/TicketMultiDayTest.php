<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Providers\Services\JwtService;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketCheckinService;
use App\Services\Ticket\TicketInventory;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos de evento de vários dias: diária (tipo preso a um dia) e
 * passaporte (tipo sem dia). Um ingresso por CPF por dia, lotação por dia e
 * uma entrada por dia (o passaporte entra em cada dia).
 */
class TicketMultiDayTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';

    private TicketEvent $event;
    private TicketEventDay $sat;
    private TicketEventDay $sun;
    private TicketType $satPass;
    private TicketType $sunPass;
    private TicketType $passport;
    private User $staff;
    private TicketOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['tickets.checkin_hours_before' => 3, 'services.api.token' => 'token-de-teste', 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $first = now()->addDays(20)->setTime(18, 0);
        $this->event = $this->ticketEvent([
            'title' => 'Festival de Inverno',
            'starts_at' => $first,
            'ends_at' => $first->copy()->addDay()->setTime(23, 59),
        ]);
        $this->sat = $this->event->days()->create(['name' => 'Sábado', 'starts_at' => $first, 'ends_at' => $first->copy()->setTime(23, 0)]);
        $this->sun = $this->event->days()->create(['starts_at' => $first->copy()->addDay(), 'ends_at' => $first->copy()->addDay()->setTime(23, 0)]);

        $this->satPass = $this->ticketType($this->event, 'Diária', ['ticket_event_day_id' => $this->sat->id]);
        $this->sunPass = $this->ticketType($this->event, 'Diária', ['ticket_event_day_id' => $this->sun->id]);
        $this->passport = $this->ticketType($this->event, 'Passaporte');
        foreach ([$this->satPass, $this->sunPass] as $type) {
            $this->ticketLot($type);
        }
        $this->ticketLot($this->passport, ['price_member' => 80, 'price_non_member' => 130]);

        $this->staff = $this->makeUser('Social');
        $this->joinSector($this->staff, 'TI');
        $this->orders = app(TicketOrderService::class);
    }

    private function buy(array $items, int $buyer = 0)
    {
        return $this->orders->create($this->event->fresh(), TicketBuyer::guest(self::GUEST_CPFS[$buyer], 'Comprador', 'c@teste.local', null), $items);
    }

    private function courtesy(array $items): \Illuminate\Support\Collection
    {
        return $this->orders->issueAtCounter($this->event->fresh(), TicketBuyer::guest(self::GUEST_CPFS[0], 'Comprador', null, null), $items, $this->staff, TicketOrderService::ISSUE_COURTESY)->tickets;
    }

    private function reasonOf(callable $call): string
    {
        try {
            $call();
        } catch (TicketException $e) {
            return $e->reason;
        }
        $this->fail('Esperava TicketException.');
    }

    public function test_tipo_sabe_se_e_diaria_ou_passaporte(): void
    {
        $this->assertSame(TicketType::VALIDITY_DAY, $this->satPass->validity());
        $this->assertSame(TicketType::VALIDITY_PASSPORT, $this->passport->validity());
        $this->assertSame('Diária · Sábado (' . $this->sat->starts_at->format('d/m') . ')', $this->satPass->fullName());
        $this->assertSame('Passaporte · Passaporte (todos os dias)', $this->passport->fullName());

        $single = $this->ticketEvent();
        $this->assertSame(TicketType::VALIDITY_EVENT, $this->ticketType($single)->validity());
    }

    public function test_mesma_pessoa_compra_diarias_de_dias_diferentes_e_nao_cruza_com_passaporte(): void
    {
        $order = $this->buy([$this->holder($this->satPass->id, 0), $this->holder($this->sunPass->id, 0)]);
        $this->assertCount(2, $order->tickets);
        $this->assertSame(160.0, (float) $order->total);

        $this->assertSame(TicketException::ALREADY_HAS_TICKET, $this->reasonOf(fn () => $this->buy([$this->holder($this->passport->id, 0)])));
        $this->assertSame(TicketException::ALREADY_HAS_TICKET, $this->reasonOf(fn () => $this->buy([$this->holder($this->satPass->id, 0)])));

        // Passaporte e diária da mesma pessoa no mesmo pedido: cruzam.
        $this->assertSame(TicketException::INVALID_HOLDER, $this->reasonOf(fn () => $this->buy([$this->holder($this->satPass->id, 1), $this->holder($this->passport->id, 1)])));

        $this->buy([$this->holder($this->passport->id, 2)]);
        $this->assertSame(TicketException::ALREADY_HAS_TICKET, $this->reasonOf(fn () => $this->buy([$this->holder($this->sunPass->id, 2)])));
    }

    public function test_lotacao_conta_por_dia(): void
    {
        $this->event->update(['capacity' => 2]);
        $this->buy([$this->holder($this->passport->id, 0), $this->holder($this->satPass->id, 1)]);

        $left = app(TicketInventory::class)->capacityLeftByDay($this->event->fresh());
        $this->assertSame([$this->sat->id => 0, $this->sun->id => 1], $left);

        try {
            $this->buy([$this->holder($this->satPass->id, 2)]);
            $this->fail('Sábado está cheio.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::CAPACITY_FULL, $e->reason);
            $this->assertSame($this->sat->id, $e->context['day_id']);
        }

        $this->buy([$this->holder($this->sunPass->id, 2)]);
        $this->assertSame(TicketException::CAPACITY_FULL, $this->reasonOf(fn () => $this->buy([$this->holder($this->passport->id, 3)])));
    }

    public function test_passaporte_entra_uma_vez_em_cada_dia(): void
    {
        $ticket = $this->courtesy([$this->holder($this->passport->id, 0)])->sole();
        $checkin = app(TicketCheckinService::class);

        Carbon::setTestNow($this->sat->starts_at->copy()->addHour());
        $this->assertTrue($checkin->evaluate($ticket->fresh())['allowed']);
        $checkin->checkIn($ticket->fresh(), $this->staff, Ticket::CHECKIN_LARA);

        $again = $checkin->evaluate($ticket->fresh());
        $this->assertSame(TicketCheckinService::REASON_USED, $again['reason']);
        $this->assertStringContainsString('hoje', $again['message']);

        // Entre os dois dias: fora da janela; o monitor nega.
        Carbon::setTestNow($this->sun->starts_at->copy()->subHours(5));
        $this->assertSame(TicketCheckinService::REASON_OUTSIDE_WINDOW, $checkin->evaluate($ticket->fresh())['reason']);
        $this->assertSame(TicketException::OUTSIDE_WINDOW, $this->reasonOf(fn () => $checkin->checkIn($ticket->fresh(), null, Ticket::CHECKIN_MONITOR)));

        Carbon::setTestNow($this->sun->starts_at->copy()->addHour());
        $used = $checkin->checkIn($ticket->fresh(), null, Ticket::CHECKIN_MONITOR);
        $this->assertSame(Ticket::STATUS_USED, $used->status);
        $this->assertSame([$this->sat->id, $this->sun->id], $used->checkins->pluck('day_key')->all());
        $this->assertSame(TicketException::ALREADY_USED, $this->reasonOf(fn () => $checkin->checkIn($ticket->fresh(), null, Ticket::CHECKIN_MONITOR)));
    }

    public function test_diaria_so_entra_no_dia_dela_e_cpf_acha_a_do_dia(): void
    {
        [$sat, $sun] = $this->courtesy([$this->holder($this->satPass->id, 0), $this->holder($this->sunPass->id, 0)])->all();
        $checkin = app(TicketCheckinService::class);

        Carbon::setTestNow($this->sun->starts_at->copy()->addHour());
        $this->assertSame(TicketCheckinService::REASON_OUTSIDE_WINDOW, $checkin->evaluate($sat->fresh())['reason']);

        Carbon::setTestNow($this->sat->starts_at->copy()->addHour());
        $found = $checkin->find(self::GUEST_CPFS[0], $this->event);
        $this->assertSame([$sat->id], $found->pluck('id')->all());
        $checkin->checkIn($found->first(), $this->staff, Ticket::CHECKIN_LARA);

        // No domingo, o CPF acha a diária de domingo (a de sábado, usada, some).
        Carbon::setTestNow($this->sun->starts_at->copy()->addHour());
        $found = $checkin->find(self::GUEST_CPFS[0]);
        $this->assertSame([$sun->id], $found->pluck('id')->all());
        $this->assertTrue($checkin->evaluate($found->first())['allowed']);

        $counts = $checkin->counts($this->event, $this->sun);
        $this->assertSame(['sold' => 1, 'used' => 0], ['sold' => $counts['sold'], 'used' => $counts['used']]);
        $this->assertSame(1, $checkin->counts($this->event, $this->sat)['used']);
    }

    public function test_api_mostra_os_dias_e_o_titulo_diz_os_dias_ja_comprados(): void
    {
        $this->member(self::CPF_ANA, 'Ana Souza');
        $app = ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'];
        $session = $app + ['Session' => (new JwtService())->generateToken(['username' => self::CPF_ANA, 'exp' => time() + 3600])];

        $this->withHeaders($app)->getJson("/api/tickets/events/{$this->event->id}")
            ->assertOk()
            ->assertJsonPath('multi_day', true)
            ->assertJsonCount(2, 'days')
            ->assertJsonPath('days.0.label', $this->sat->label())
            ->assertJsonPath('types.0.validity', TicketType::VALIDITY_DAY)
            ->assertJsonPath('types.0.day_id', $this->sat->id)
            ->assertJsonPath('types.2.validity', TicketType::VALIDITY_PASSPORT);

        $this->withHeaders($session)->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->satPass->id]]])
            ->assertCreated()
            ->assertJsonPath('tickets.0.validity', TicketType::VALIDITY_DAY)
            ->assertJsonPath('tickets.0.day.id', $this->sat->id);

        $this->withHeaders($session)->getJson("/api/tickets/my-title?event_id={$this->event->id}")
            ->assertJsonPath('people.0.has_ticket', true)
            ->assertJsonPath('people.0.days_taken', [$this->sat->id]);

        $this->withHeaders($session)->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->passport->id]]])
            ->assertStatus(409)
            ->assertJsonPath('reason', TicketException::ALREADY_HAS_TICKET);
        $this->withHeaders($session)->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->sunPass->id]]])
            ->assertCreated();
    }

    public function test_painel_cadastra_dias_e_protege_o_que_ja_foi_vendido(): void
    {
        $event = $this->ticketEvent(['starts_at' => now()->addDays(30)->setTime(10, 0), 'ends_at' => now()->addDays(32)->setTime(22, 0)]);
        $day = fn (int $plus, string $from, string $to) => [
            'starts_at' => now()->addDays($plus)->format('Y-m-d') . "T{$from}",
            'ends_at' => now()->addDays($plus)->format('Y-m-d') . "T{$to}",
        ];

        $this->actingAs($this->staff)->post(route('tickets.days.store', $event), $day(30, '10:00', '22:00'))->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('tickets.days.store', $event), $day(30, '20:00', '23:00'))->assertSessionHasErrors('starts_at');
        $this->actingAs($this->staff)->post(route('tickets.days.store', $event), $day(33, '10:00', '12:00'))->assertSessionHasErrors('starts_at');
        $this->actingAs($this->staff)->post(route('tickets.days.store', $event), $day(31, '10:00', '22:00') + ['name' => 'Domingo'])->assertSessionHasNoErrors();
        [$first, $second] = $event->days()->get()->all();

        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Diária', 'ticket_event_day_id' => $first->id])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Passaporte', 'ticket_event_day_id' => ''])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Alheio', 'ticket_event_day_id' => $this->sat->id])->assertSessionHasErrors('ticket_event_day_id');
        $daily = $event->types()->where('ticket_event_day_id', $first->id)->sole();
        $this->assertSame(TicketType::VALIDITY_PASSPORT, $event->types()->whereNull('ticket_event_day_id')->sole()->validity());

        $this->actingAs($this->staff)->delete(route('tickets.days.destroy', [$event, $first]))->assertSessionHasErrors('day');

        // Diária vendida não muda de dia.
        $this->ticketLot($daily);
        $this->orders->issueAtCounter($event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Comprador', null, null), [$this->holder($daily->id, 0)], $this->staff, TicketOrderService::ISSUE_COURTESY);
        $this->actingAs($this->staff)->put(route('tickets.types.update', [$event, $daily]), ['name' => 'Diária', 'active' => 1, 'ticket_event_day_id' => $second->id])->assertSessionHasErrors('ticket_event_day_id');
        $this->actingAs($this->staff)->put(route('tickets.types.update', [$event, $daily]), ['name' => 'Diária sábado', 'active' => 1, 'ticket_event_day_id' => $first->id])->assertSessionHasNoErrors();

        // O evento não encolhe para deixar um dia de fora.
        $this->actingAs($this->staff)->put(route('tickets.update', $event), [
            'title' => $event->title, 'starts_at' => $event->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $event->starts_at->copy()->addHours(5)->format('Y-m-d\TH:i'),
            'sales_starts_at' => $event->sales_starts_at->format('Y-m-d\TH:i'), 'sales_ends_at' => $event->sales_ends_at->format('Y-m-d\TH:i'), 'max_per_order' => 4,
        ])->assertSessionHasErrors('starts_at');

        $this->actingAs($this->staff)->delete(route('tickets.days.destroy', [$event, $second]))->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->get(route('tickets.show', $event))
            ->assertOk()
            ->assertSee('Dias do evento')
            ->assertSee('Passaporte (todos os dias)');
    }
}

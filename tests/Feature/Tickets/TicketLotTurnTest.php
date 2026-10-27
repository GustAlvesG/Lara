<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\ConfirmationRequired;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketInventory;
use App\Services\Ticket\TicketOrderService;
use App\Services\Ticket\TicketSetupService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Lote sem limite de ingressos (`quantity` nulo) e "Virar lote agora" (o lote
 * à venda de cada tipo fecha e o seguinte abre).
 */
class TicketLotTurnTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private TicketEvent $event;
    private TicketType $pista;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => 'token-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->event = $this->ticketEvent();
        $this->pista = $this->ticketType($this->event);
        $this->staff = $this->makeUser('Social');
        $this->joinSector($this->staff, 'TI');
    }

    private function buy(int $index)
    {
        return app(TicketOrderService::class)->create($this->event->fresh(), TicketBuyer::guest(self::GUEST_CPFS[$index], 'Comprador', 'c@teste.local', null), [$this->holder($this->pista->id, $index)]);
    }

    public function test_lote_sem_quantidade_nao_esgota(): void
    {
        $lot = $this->ticketLot($this->pista, ['quantity' => null]);

        foreach ([0, 1, 2] as $i) {
            $this->assertSame($lot->id, $this->buy($i)->tickets->sole()->ticket_lot_id);
        }

        $row = collect(app(TicketInventory::class)->summary($this->event->fresh()))->sole();
        $this->assertNull($row['remaining']);
        $this->assertNull($row['quantity']);

        $this->withHeaders(['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'])
            ->getJson("/api/tickets/events/{$this->event->id}")
            ->assertOk()
            ->assertJsonPath('types.0.available', true)
            ->assertJsonPath('types.0.lot.remaining', null);

        // A lotação do evento continua valendo.
        $this->event->update(['capacity' => 3]);
        $this->expectExceptionMessage('lotação');
        $this->buy(3);
    }

    public function test_painel_grava_lote_sem_quantidade(): void
    {
        $this->actingAs($this->staff)->post(route('tickets.types.lots.store', [$this->event, $this->pista]), [
            'name' => '1º lote', 'sequence' => 1, 'quantity' => '', 'price_member' => 50, 'price_non_member' => 80,
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->pista->lots()->sole()->quantity);
        $this->actingAs($this->staff)->get(route('tickets.show', $this->event))->assertOk()->assertSee('sem limite');
    }

    public function test_virar_lote_fecha_o_atual_e_abre_o_seguinte(): void
    {
        $first = $this->ticketLot($this->pista, ['quantity' => null]);
        $second = $this->ticketLot($this->pista, ['price_member' => 70, 'price_non_member' => 100, 'starts_at' => now()->addDays(5)]);
        // Tipo com um lote só: continua nele.
        $mesa = $this->ticketType($this->event, 'Mesa');
        $only = $this->ticketLot($mesa);
        $this->buy(0);

        $setup = app(TicketSetupService::class);
        try {
            $setup->turnLots($this->event);
            $this->fail('Virar lote pede confirmação.');
        } catch (ConfirmationRequired $e) {
            $text = implode(' ', $e->warnings);
            $this->assertStringContainsString('o 1º lote fecha agora e o 2º lote abre', $text);
            $this->assertStringContainsString('Mesa: o 1º lote é o último lote', $text);
        }
        $this->assertNull($first->fresh()->ends_at);

        $plan = $setup->turnLots($this->event, force: true);
        $this->assertCount(1, $plan);

        $inventory = app(TicketInventory::class);
        $summary = collect($inventory->summary($this->event->fresh()))->keyBy(fn ($row) => $row['type']->id);
        $this->assertSame($second->id, $summary[$this->pista->id]['lot']->id);
        $this->assertSame($only->id, $summary[$mesa->id]['lot']->id);
        $this->assertFalse($second->fresh()->starts_at->isFuture());

        // O pedido seguinte já vai no 2º lote, com o preço dele.
        $ticket = $this->buy(1)->tickets->sole();
        $this->assertSame($second->id, $ticket->ticket_lot_id);
        $this->assertSame(100.0, (float) $ticket->amount);
    }

    public function test_sem_lote_seguinte_recusa_e_tela_vira_com_confirmacao(): void
    {
        $this->ticketLot($this->pista);

        $this->actingAs($this->staff)->post(route('tickets.lots.turn', $this->event))->assertSessionHasErrors('lots');

        $second = $this->ticketLot($this->pista);
        $this->actingAs($this->staff)->post(route('tickets.lots.turn', $this->event))->assertSessionHas('confirmation');
        $this->actingAs($this->staff)->post(route('tickets.lots.turn', $this->event), ['force' => 1])
            ->assertRedirect(route('tickets.show', $this->event))
            ->assertSessionHas('success');

        $current = collect(app(TicketInventory::class)->summary($this->event->fresh()))->sole()['lot'];
        $this->assertSame($second->id, $current->id);

        $this->actingAs($this->makeUser('Sem Acesso'))->post(route('tickets.lots.turn', $this->event), ['force' => 1])->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Tickets;

use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos no painel: evento em rascunho, tipos, lotes e situação; as
 * recusas (apagar com ingresso, publicar sem nada à venda) e os avisos.
 */
class TicketPanelTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTicketSchema();
        $this->fakeIntegrations();

        // TI tem acesso total: alcança todas as `ingressos.*`.
        $this->staff = $this->makeUser('Pessoa do Social');
        $this->joinSector($this->staff, 'TI');
    }

    public function test_sem_permissao_nao_abre(): void
    {
        $this->actingAs($this->makeUser('Sem Acesso'))->get(route('tickets.index'))->assertForbidden();
    }

    public function test_cria_evento_tipo_e_lote_e_publica(): void
    {
        $this->actingAs($this->staff)->post(route('tickets.store'), [
            'title' => 'Festa Junina',
            'venue' => 'Quadra Coberta',
            'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(10)->addHours(5)->format('Y-m-d\TH:i'),
            'sales_starts_at' => now()->format('Y-m-d\TH:i'),
            'sales_ends_at' => now()->addDays(9)->format('Y-m-d\TH:i'),
            'max_per_order' => 6,
        ])->assertRedirect();

        $event = TicketEvent::where('title', 'Festa Junina')->firstOrFail();
        $this->assertSame(TicketEvent::STATUS_DRAFT, $event->status);

        // Sem tipo com lote, não publica.
        $this->actingAs($this->staff)->put(route('tickets.status', $event), ['status' => TicketEvent::STATUS_PUBLISHED])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->staff)->post(route('tickets.types.store', $event), ['name' => 'Pista'])->assertRedirect();
        $type = $event->types()->firstOrFail();

        $this->actingAs($this->staff)->post(route('tickets.types.lots.store', [$event, $type]), [
            'name' => '1º lote', 'sequence' => 1, 'quantity' => 200, 'price_member' => '30.00', 'price_non_member' => '',
        ])->assertRedirect();
        $lot = $type->lots()->firstOrFail();
        $this->assertNull($lot->price_non_member);

        $this->actingAs($this->staff)->put(route('tickets.status', $event), ['status' => TicketEvent::STATUS_PUBLISHED])->assertRedirect();
        $this->assertSame(TicketEvent::STATUS_PUBLISHED, $event->fresh()->status);

        $this->actingAs($this->staff)->get(route('tickets.show', $event))
            ->assertOk()->assertSee('Pista')->assertSee('1º lote')->assertSee('Só sócios');
        $this->actingAs($this->staff)->get(route('tickets.index'))->assertOk()->assertSee('Festa Junina');
    }

    public function test_lista_principal_mostra_publicados_e_depois_rascunhos(): void
    {
        $this->ticketEvent(['title' => 'Evento Rascunho', 'status' => TicketEvent::STATUS_DRAFT, 'starts_at' => now()->addDays(40), 'ends_at' => now()->addDays(40)->addHours(4)]);
        $this->ticketEvent(['title' => 'Evento Publicado', 'status' => TicketEvent::STATUS_PUBLISHED]);
        $this->ticketEvent(['title' => 'Evento Encerrado', 'status' => TicketEvent::STATUS_FINISHED]);
        $this->ticketEvent(['title' => 'Evento Cancelado', 'status' => TicketEvent::STATUS_CANCELLED]);

        // Publicado antes do rascunho, mesmo o rascunho começando depois.
        $this->actingAs($this->staff)->get(route('tickets.index'))->assertOk()
            ->assertSeeInOrder(['Evento Publicado', 'Evento Rascunho'])
            ->assertDontSee('Evento Encerrado')
            ->assertDontSee('Evento Cancelado');

        $this->actingAs($this->staff)->get(route('tickets.index', ['status' => TicketEvent::STATUS_FINISHED]))->assertOk()
            ->assertSee('Evento Encerrado')->assertDontSee('Evento Publicado');

        $this->actingAs($this->staff)->get(route('tickets.index', ['status' => 'all']))->assertOk()
            ->assertSee('Evento Encerrado')->assertSee('Evento Cancelado')->assertSee('Evento Rascunho');
    }

    public function test_lista_conta_vendidos_e_cortesias_separados(): void
    {
        $event = $this->ticketEvent(['capacity' => 100]);
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['quantity' => 10]);
        $orders = app(TicketOrderService::class);

        // Duas vendas pagas (baixa manual) e uma cortesia.
        $sale = $orders->create($event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla', 'c@x.local', null), [$this->holder($type->id, 0), $this->holder($type->id, 1)]);
        $orders->recordManual($sale, ['payment_method' => 'manual_pix'], $this->staff);
        $orders->create($event, TicketBuyer::guest(self::GUEST_CPFS[2], 'Bia', 'b@x.local', null), [$this->holder($type->id, 2)], $this->staff, force: true, courtesy: true);

        $this->actingAs($this->staff)->get(route('tickets.index'))->assertOk()
            ->assertViewHas('events', function ($events) {
                $row = $events->getCollection()->sole();

                return $row->sold_count === 2 && $row->courtesy_count === 1;
            })
            ->assertSee('Cortesias')
            ->assertSee('3 de 100 ocupados');
    }

    public function test_tipo_ou_lote_com_ingresso_nao_apaga_e_quantidade_abaixo_do_ocupado_pede_confirmacao(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $lot = $this->ticketLot($type, ['quantity' => 5]);
        app(TicketOrderService::class)->create($event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla', 'c@x.local', null), [$this->holder($type->id, 0), $this->holder($type->id, 1)]);

        $this->actingAs($this->staff)->delete(route('tickets.types.lots.destroy', [$event, $type, $lot]))->assertSessionHasErrors('lot');
        $this->actingAs($this->staff)->delete(route('tickets.types.destroy', [$event, $type]))->assertSessionHasErrors('type');

        $data = ['name' => '1º lote', 'sequence' => 1, 'quantity' => 1, 'price_member' => 50, 'price_non_member' => 80, 'active' => 1];
        $this->actingAs($this->staff)->put(route('tickets.types.lots.update', [$event, $type, $lot]), $data)->assertSessionHas('confirmation');
        $this->assertSame(5, $lot->fresh()->quantity);

        $this->actingAs($this->staff)->put(route('tickets.types.lots.update', [$event, $type, $lot]), $data + ['force' => 1])->assertRedirect();
        $this->assertSame(1, $lot->fresh()->quantity);
    }

    public function test_lote_de_outro_evento_nao_e_alcancado(): void
    {
        $event = $this->ticketEvent();
        $other = $this->ticketEvent(['title' => 'Outro']);
        $type = $this->ticketType($other);
        $lot = $this->ticketLot($type);

        $this->actingAs($this->staff)->delete(route('tickets.types.lots.destroy', [$event, $type, $lot]))->assertNotFound();
        $this->assertNotNull(TicketLot::find($lot->id));
    }
}

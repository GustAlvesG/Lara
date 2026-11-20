<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketHolderList;
use App\Services\Ticket\TicketOrderService;
use App\Support\Cpf;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos › Listagem: nome, CPF e ingresso dos compradores, por dia e tipo,
 * só dos pagos; PDF e CSV com permissão própria (`ingressos.listagem`).
 */
class TicketHolderListTest extends TestCase
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
        $this->staff = $this->makeUser('Social');
        $this->joinSector($this->staff, 'TI');
    }

    private function courtesy($event, array $items)
    {
        return app(TicketOrderService::class)->issueAtCounter(
            $event->fresh(),
            TicketBuyer::guest(self::GUEST_CPFS[0], 'Comprador', null, null),
            $items,
            $this->staff,
            TicketOrderService::ISSUE_COURTESY,
        );
    }

    public function test_agrupa_por_dia_e_tipo_so_os_pagos_com_cpf_inteiro(): void
    {
        $first = now()->addDays(20)->setTime(18, 0);
        $event = $this->ticketEvent(['starts_at' => $first, 'ends_at' => $first->copy()->addDay()->setTime(23, 59)]);
        $sat = $event->days()->create(['name' => 'Sábado', 'starts_at' => $first, 'ends_at' => $first->copy()->setTime(23, 0)]);
        $sun = $event->days()->create(['starts_at' => $first->copy()->addDay(), 'ends_at' => $first->copy()->addDay()->setTime(23, 0)]);
        $satPass = $this->ticketType($event, 'Diária', ['ticket_event_day_id' => $sat->id]);
        $sunPass = $this->ticketType($event, 'Diária', ['ticket_event_day_id' => $sun->id]);
        $passport = $this->ticketType($event, 'Passaporte');
        foreach ([$satPass, $sunPass, $passport] as $type) {
            $this->ticketLot($type);
        }

        // Pagos: dois no sábado, um no domingo, um passaporte.
        $this->courtesy($event, [
            $this->holder($sunPass->id, 1, 'Zeca'),
            $this->holder($satPass->id, 2, 'Bruna'),
            $this->holder($satPass->id, 3, 'Ana'),
            $this->holder($passport->id, 0, 'Paulo'),
        ]);
        // Pendente (site, não pago): fica de fora.
        app(TicketOrderService::class)->create($event->fresh(), TicketBuyer::guest(self::GUEST_CPFS[2], 'Pendente', 'p@teste.local', null), [$this->holder($sunPass->id, 2, 'Pendente')]);

        $groups = app(TicketHolderList::class)->groups($event->fresh());

        $this->assertSame(['Sábado (' . $first->format('d/m') . ')', $sun->fresh()->label(), 'Passaporte (todos os dias)'], $groups->pluck('label')->all());
        $this->assertSame([2, 1, 1], $groups->pluck('count')->all());
        $saturday = $groups[0]['types'][0];
        $this->assertSame('Diária', $saturday['label']);
        $this->assertSame(['Ana', 'Bruna'], $saturday['rows']->pluck('name')->all());
        $this->assertSame(Cpf::format(self::GUEST_CPFS[3]), $saturday['rows'][0]['cpf']);
        $this->assertMatchesRegularExpression('/^ING-[A-Z2-9]{8}$/', $saturday['rows'][0]['ticket']);

        $csv = $this->actingAs($this->staff)->get(route('tickets.holders.csv', $event))->assertOk()->streamedContent();
        $this->assertStringContainsString('Dia;"Tipo de ingresso";Nome;CPF;Ingresso', $csv);
        $this->assertStringContainsString(Cpf::format(self::GUEST_CPFS[1]), $csv);
        $this->assertStringNotContainsString('Pendente', $csv);
        $this->assertSame(5, substr_count(trim($csv), "\n") + 1);

        $this->actingAs($this->staff)->get(route('tickets.holders.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staff)->get(route('tickets.show', $event))->assertOk()->assertSee(route('tickets.holders.pdf', $event));
    }

    public function test_evento_sem_dias_tem_um_grupo_so(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $this->courtesy($event, [$this->holder($type->id, 1, 'Carla')]);

        $groups = app(TicketHolderList::class)->groups($event->fresh());

        $this->assertCount(1, $groups);
        $this->assertSame($event->starts_at->format('d/m/Y'), $groups[0]['label']);
        $this->assertSame('Carla', $groups[0]['types'][0]['rows'][0]['name']);
    }

    public function test_sem_a_permissao_nao_extrai(): void
    {
        $event = $this->ticketEvent();
        $other = $this->makeUser('Sem permissao');

        $this->actingAs($other)->get(route('tickets.holders.pdf', $event))->assertForbidden();
        $this->actingAs($other)->get(route('tickets.holders.csv', $event))->assertForbidden();
    }
}

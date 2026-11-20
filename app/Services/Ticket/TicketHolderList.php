<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Support\Cpf;
use Illuminate\Support\Collection;

/**
 * Listagem dos compradores de um evento (permissão `ingressos.listagem`):
 * nome, CPF e ingresso de cada ingresso pago (válido ou utilizado), agrupados
 * por dia e, dentro do dia, por tipo de ingresso.
 *
 * O nome e o CPF são os do titular do ingresso (a pessoa que entra), não os de
 * quem pagou o pedido. O CPF sai inteiro: a lista é para conferir na porta.
 *
 * Grupos de dia, em ordem: os dias do evento (diárias), depois o passaporte
 * (tipo sem dia num evento com dias). No evento sem dias, um grupo só.
 * Lugar marcado (mesa, bistrô, camarote) entra no tipo dele, com o lugar ao
 * lado do código.
 */
class TicketHolderList
{
    /**
     * @return Collection<int, array{label: string, count: int, types: Collection<int, array{label: string, rows: Collection<int, array{name: string, cpf: string, ticket: string}>}>}>
     */
    public function groups(TicketEvent $event): Collection
    {
        $event->loadMissing('days');

        $tickets = Ticket::with(['type.day', 'seat'])
            ->where('ticket_event_id', $event->id)
            ->whereIn('status', Ticket::SOLD)
            ->get();

        // Ordem dos dias: a do calendário; passaporte por último.
        $dayOrder = $event->days->sortBy('starts_at')->values()->pluck('id')->flip();
        $hasDays = $event->hasDays();

        return $tickets
            ->groupBy(fn (Ticket $t) => (string) ($t->type->ticket_event_day_id ?? 0))
            ->sortBy(fn ($group, $dayId) => $dayId === '0' ? PHP_INT_MAX : ($dayOrder[(int) $dayId] ?? PHP_INT_MAX - 1))
            ->map(function (Collection $dayTickets, $dayId) use ($event, $hasDays) {
                $day = $dayTickets->first()->type->day;

                return [
                    'label' => $this->dayLabel($event, $day, $hasDays),
                    'count' => $dayTickets->count(),
                    'types' => $dayTickets
                        ->groupBy('ticket_type_id')
                        ->sortBy(fn (Collection $g) => [$g->first()->type->sort_order ?? 0, $g->first()->type->name])
                        ->map(fn (Collection $g) => [
                            'label' => $g->first()->type->name,
                            'rows' => $g
                                ->sortBy(fn (Ticket $t) => mb_strtolower((string) $t->holder_name))
                                ->map(fn (Ticket $t) => $this->row($t))
                                ->values(),
                        ])
                        ->values(),
                ];
            })
            ->values();
    }

    /**
     * Linhas do CSV: Dia; Tipo; Nome; CPF; Ingresso — na mesma ordem dos grupos.
     *
     * @return array<int, array<int, string>>
     */
    public function csvRows(TicketEvent $event): array
    {
        $rows = [['Dia', 'Tipo de ingresso', 'Nome', 'CPF', 'Ingresso']];

        foreach ($this->groups($event) as $day) {
            foreach ($day['types'] as $type) {
                foreach ($type['rows'] as $row) {
                    $rows[] = [$day['label'], $type['label'], $row['name'], $row['cpf'], $row['ticket']];
                }
            }
        }

        return $rows;
    }

    private function dayLabel(TicketEvent $event, ?TicketEventDay $day, bool $hasDays): string
    {
        if ($day) {
            return $day->label();
        }

        return $hasDays ? 'Passaporte (todos os dias)' : $event->starts_at->format('d/m/Y');
    }

    /** @return array{name: string, cpf: string, ticket: string} */
    private function row(Ticket $t): array
    {
        return [
            'name' => (string) ($t->holder_name ?: '—'),
            'cpf' => $t->holder_cpf ? Cpf::format($t->holder_cpf) : 'sem CPF',
            'ticket' => $t->seat ? "{$t->code} · {$t->seat->label}" : (string) $t->code,
        ];
    }
}

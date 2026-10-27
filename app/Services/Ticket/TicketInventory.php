<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketType;
use Illuminate\Support\Carbon;

/**
 * Estoque e lote vigente.
 *
 * Ocupa lugar o ingresso pago (válido ou usado) e o pendente de pedido ainda
 * dentro do prazo (`Ticket::occupying`). O lote vigente de um tipo é o de
 * menor `sequence` que está ativo, dentro da janela e com sobra: o lote fecha
 * pela data ou quando esgota, o que vier primeiro, e o seguinte abre na hora.
 *
 * A lotação do evento conta por dia no evento de vários dias: cada dia soma as
 * diárias dele e os passaportes.
 *
 * Só conta; quem trava as linhas para a conta valer na gravação é o
 * TicketOrderService.
 */
class TicketInventory
{
    /**
     * Lugares ocupados por lote.
     *
     * @param  iterable<int>  $lotIds
     * @return array<int, int> lot_id => ocupados
     */
    public function occupiedByLot(iterable $lotIds, ?int $exceptOrderId = null): array
    {
        $ids = collect($lotIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Ticket::occupying()
            ->whereIn('ticket_lot_id', $ids)
            ->when($exceptOrderId, fn ($q) => $q->where('ticket_order_id', '!=', $exceptOrderId))
            ->selectRaw('ticket_lot_id, count(*) as total')
            ->groupBy('ticket_lot_id')
            ->pluck('total', 'ticket_lot_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Lugares ocupados por dia, para a lotação: no evento sem dias, `[0 => n]`
     * (todos os tipos); no de vários dias, `[dia => diárias do dia +
     * passaportes]`.
     *
     * @return array<int, int>
     */
    public function occupiedByDay(TicketEvent $event, ?int $exceptOrderId = null): array
    {
        $event->loadMissing('days');

        $rows = Ticket::occupying()
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where('tickets.ticket_event_id', $event->id)
            ->when($exceptOrderId, fn ($q) => $q->where('tickets.ticket_order_id', '!=', $exceptOrderId))
            ->selectRaw('ticket_types.ticket_event_day_id as day_id, count(*) as total')
            ->groupBy('ticket_types.ticket_event_day_id')
            ->get();

        if (! $event->hasDays()) {
            return [0 => (int) $rows->sum('total')];
        }

        $all = (int) $rows->whereNull('day_id')->sum('total');

        return $event->days->mapWithKeys(fn (TicketEventDay $day) => [
            $day->id => $all + (int) $rows->where('day_id', $day->id)->sum('total'),
        ])->all();
    }

    /**
     * Os dias em que os ingressos novos (o dia de cada um; null = todos os
     * dias) passam da lotação.
     *
     * @param  list<?int>  $newDayIds
     * @return list<array{day: ?TicketEventDay, taken: int, left: int}>
     */
    public function capacityOverflow(TicketEvent $event, array $newDayIds, ?int $exceptOrderId = null): array
    {
        if ($event->capacity === null) {
            return [];
        }

        $overflow = [];

        foreach ($this->occupiedByDay($event, $exceptOrderId) as $dayId => $taken) {
            $adding = count(array_filter($newDayIds, fn (?int $d) => $dayId === 0 || TicketType::daysOverlap($d, $dayId)));

            if ($adding > 0 && $taken + $adding > $event->capacity) {
                $overflow[] = [
                    'day' => $dayId === 0 ? null : $event->days->firstWhere('id', $dayId),
                    'taken' => $taken,
                    'left' => max(0, $event->capacity - $taken),
                ];
            }
        }

        return $overflow;
    }

    /**
     * O lote à venda agora, ou null (esgotado, ou o próximo ainda não abriu).
     *
     * @param  array<int, int>  $occupied  lot_id => ocupados (inclua o que o pedido em montagem já pegou)
     */
    public function currentLot(TicketType $type, array $occupied, ?Carbon $at = null): ?TicketLot
    {
        $at ??= now();

        foreach ($type->lots as $lot) {
            if ($lot->active && $lot->withinDates($at) && $this->remaining($lot, $occupied) > 0) {
                return $lot;
            }
        }

        return null;
    }

    /** O próximo lote que ainda vai abrir pela data (para o site mostrar "a partir de"). */
    public function nextLot(TicketType $type, array $occupied, ?Carbon $at = null): ?TicketLot
    {
        $at ??= now();

        return $type->lots->first(fn (TicketLot $lot) => $lot->active
            && $lot->starts_at !== null
            && $lot->starts_at->greaterThan($at)
            && $this->remaining($lot, $occupied) > 0);
    }

    /** @param  array<int, int>  $occupied */
    public function remaining(TicketLot $lot, array $occupied): int
    {
        return max(0, $lot->quantity - ($occupied[$lot->id] ?? 0));
    }

    /**
     * Situação de venda de cada tipo do evento, para o site e para a tela.
     *
     * @return list<array{type: TicketType, lot: ?TicketLot, remaining: ?int, next_lot: ?TicketLot, occupied: int, quantity: int}>
     */
    public function summary(TicketEvent $event, ?Carbon $at = null): array
    {
        $event->loadMissing('types.lots');
        $occupied = $this->occupiedByLot($event->types->flatMap->lots->pluck('id'));

        return $event->types->map(function (TicketType $type) use ($occupied, $at) {
            $lot = $type->active ? $this->currentLot($type, $occupied, $at) : null;

            return [
                'type' => $type,
                'lot' => $lot,
                'remaining' => $lot ? $this->remaining($lot, $occupied) : null,
                'next_lot' => $type->active && ! $lot ? $this->nextLot($type, $occupied, $at) : null,
                'occupied' => (int) $type->lots->sum(fn (TicketLot $l) => $occupied[$l->id] ?? 0),
                'quantity' => (int) $type->lots->where('active', true)->sum('quantity'),
            ];
        })->values()->all();
    }

    /**
     * Lugares que ainda cabem no evento; null = sem lotação. No evento de
     * vários dias, o do dia mais cheio (o que cabe de passaporte).
     */
    public function capacityLeft(TicketEvent $event): ?int
    {
        $left = $this->capacityLeftByDay($event);

        return $left === null ? null : (int) min($left);
    }

    /**
     * Lugares que ainda cabem em cada dia (`[0 => n]` no evento sem dias);
     * null = sem lotação.
     *
     * @return array<int, int>|null
     */
    public function capacityLeftByDay(TicketEvent $event): ?array
    {
        if ($event->capacity === null) {
            return null;
        }

        return array_map(fn (int $taken) => max(0, $event->capacity - $taken), $this->occupiedByDay($event));
    }
}

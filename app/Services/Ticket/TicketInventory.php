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
 * Lote sem quantidade (`quantity` nulo) não esgota: fecha pela data ou pelo
 * "Virar lote agora" (TicketSetupService::turnLots).
 *
 * A lotação do evento conta por dia no evento de vários dias: cada dia soma as
 * diárias dele e os passaportes.
 *
 * Lugar marcado (mesa, bistrô, camarote): o ingresso do lugar não conta na
 * lotação (não é pessoa). O tipo de lugar esgota quando os lugares que ele
 * vende acabam no dia, além da quantidade do lote (`seats_free` no resumo).
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
            ->people()
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
            if ($lot->active && $lot->withinDates($at) && $this->hasRoom($lot, $occupied)) {
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
            && $this->hasRoom($lot, $occupied));
    }

    /**
     * O que sobra no lote; null = lote sem limite de ingressos.
     *
     * @param  array<int, int>  $occupied
     */
    public function remaining(TicketLot $lot, array $occupied): ?int
    {
        return $lot->quantity === null ? null : max(0, $lot->quantity - ($occupied[$lot->id] ?? 0));
    }

    /** @param  array<int, int>  $occupied */
    public function hasRoom(TicketLot $lot, array $occupied): bool
    {
        $remaining = $this->remaining($lot, $occupied);

        return $remaining === null || $remaining > 0;
    }

    /**
     * Quem ocupa cada lugar marcado do evento, por dia (`day_key`: o dia da
     * diária, 0 no evento sem dias).
     *
     * @return array<int, array<int, Ticket>> day_key => [seat_id => ingresso]
     */
    public function seatOccupants(TicketEvent $event, ?int $exceptOrderId = null): array
    {
        $occupants = [];

        Ticket::occupying()
            ->with('type', 'order')
            ->where('tickets.ticket_event_id', $event->id)
            ->whereNotNull('tickets.ticket_seat_id')
            ->when($exceptOrderId, fn ($q) => $q->where('tickets.ticket_order_id', '!=', $exceptOrderId))
            ->get()
            ->each(function (Ticket $ticket) use (&$occupants) {
                $occupants[$ticket->type->dayKey()][$ticket->ticket_seat_id] = $ticket;
            });

        return $occupants;
    }

    /**
     * Quantos lugares o tipo de lugar marcado ainda tem livres no dia dele.
     *
     * @param  array<int, array<int, Ticket>>  $occupants  de seatOccupants()
     */
    public function seatsFree(TicketType $type, iterable $seats, array $occupants): int
    {
        $taken = $occupants[$type->dayKey()] ?? [];

        return $type->sellableSeats($seats)->reject(fn ($seat) => isset($taken[$seat->id]))->count();
    }

    /**
     * Situação de venda de cada tipo do evento, para o site e para a tela.
     *
     * `remaining` e `quantity` nulos = sem limite (lote vigente sem quantidade;
     * algum lote ativo do tipo sem quantidade). No tipo de lugar marcado,
     * `seats_free` são os lugares livres no dia (e `remaining` não passa
     * deles); sem lugar livre, o tipo fica sem lote à venda.
     *
     * @return list<array{type: TicketType, lot: ?TicketLot, remaining: ?int, next_lot: ?TicketLot, occupied: int, quantity: ?int, seats_free: ?int}>
     */
    public function summary(TicketEvent $event, ?Carbon $at = null): array
    {
        $event->loadMissing('types.lots', 'maps.seats');
        $occupied = $this->occupiedByLot($event->types->flatMap->lots->pluck('id'));
        $seats = $event->maps->flatMap->seats;
        $occupants = $event->types->contains(fn (TicketType $t) => $t->isSeated()) ? $this->seatOccupants($event) : [];

        return $event->types->map(function (TicketType $type) use ($occupied, $at, $seats, $occupants) {
            $lot = $type->active ? $this->currentLot($type, $occupied, $at) : null;
            $remaining = $lot ? $this->remaining($lot, $occupied) : null;
            $seatsFree = $type->isSeated() ? $this->seatsFree($type, $seats, $occupants) : null;

            if ($seatsFree !== null && $lot) {
                $remaining = $remaining === null ? $seatsFree : min($remaining, $seatsFree);
                if ($seatsFree === 0) {
                    $lot = null;
                    $remaining = null;
                }
            }

            return [
                'type' => $type,
                'lot' => $lot,
                'remaining' => $remaining,
                'seats_free' => $seatsFree,
                'next_lot' => $type->active && ! $lot && $seatsFree !== 0 ? $this->nextLot($type, $occupied, $at) : null,
                'occupied' => (int) $type->lots->sum(fn (TicketLot $l) => $occupied[$l->id] ?? 0),
                'quantity' => $type->lots->where('active', true)->contains(fn (TicketLot $l) => $l->quantity === null)
                    ? null
                    : (int) $type->lots->where('active', true)->sum('quantity'),
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

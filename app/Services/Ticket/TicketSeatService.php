<?php

namespace App\Services\Ticket;

use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketOrderLog;
use App\Models\Ticket\TicketSeat;
use App\Models\Ticket\TicketSeatClaim;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Lugar marcado (mesas, bistrôs, camarotes) — docs/funcionalidades/ingressos.md.
 *
 * Duas proteções contra o lugar vendido duas vezes no mesmo dia:
 *   1. a trava da linha do evento (`lockForUpdate`), que o TicketOrderService
 *      já usa em pedido e pagamento;
 *   2. o índice único (lugar, dia) de `ticket_seat_claims`: `claim()` grava a
 *      linha e, se outro ingresso que ainda ocupa estiver nela, recusa
 *      `seat_taken`.
 *
 * Ocupa o lugar quem ocupa estoque (`Ticket::occupying`): pago, ou pendente
 * dentro do prazo. A linha de quem deixou de ocupar (pedido vencido antes do
 * comando de expirar, por exemplo) é apagada quando outra pessoa pede o lugar.
 *
 * Mesa cativa (`TicketSeat::captiveFor`): lugar guardado para uma matrícula
 * até o evento liberar. No mapa, aparece `captive` para os outros e livre para
 * quem é da matrícula (`$title` no `layout`).
 */
class TicketSeatService
{
    public const STATUS_FREE = 'free';
    public const STATUS_HELD = 'held';
    public const STATUS_SOLD = 'sold';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_CAPTIVE = 'captive';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_FREE => 'Livre',
        self::STATUS_HELD => 'Reservado (aguardando pagamento)',
        self::STATUS_SOLD => 'Vendido',
        self::STATUS_UNAVAILABLE => 'Indisponível',
        self::STATUS_CAPTIVE => 'Mesa cativa (reservada para um sócio)',
    ];

    /** De quantos em quantos segundos o site recarrega o mapa enquanto a pessoa escolhe. */
    public const REFRESH_SECONDS = 15;

    public function __construct(private TicketInventory $inventory)
    {
    }

    /**
     * Prende o lugar do ingresso no dia. Chame dentro da transação, com o
     * evento travado.
     *
     * @throws TicketException seat_taken (outro ingresso que ainda ocupa está no lugar)
     */
    public function claim(Ticket $ticket, int $dayKey): void
    {
        $current = TicketSeatClaim::where('ticket_seat_id', $ticket->ticket_seat_id)->where('day_key', $dayKey)->first();

        if ($current && $current->ticket_id === $ticket->id) {
            return;
        }

        if ($current) {
            if (Ticket::occupying()->whereKey($current->ticket_id)->exists()) {
                throw $this->taken([$ticket->ticket_seat_id]);
            }
            // Reserva morta: o pedido dela venceu, desistiu ou foi cancelado.
            $current->delete();
        }

        // Um lugar por ingresso: troca de lugar solta o anterior.
        TicketSeatClaim::where('ticket_id', $ticket->id)->delete();

        try {
            TicketSeatClaim::create(['ticket_seat_id' => $ticket->ticket_seat_id, 'ticket_id' => $ticket->id, 'day_key' => $dayKey]);
        } catch (UniqueConstraintViolationException) {
            // Outra gravação escapou da trava e chegou antes.
            throw $this->taken([$ticket->ticket_seat_id]);
        }
    }

    /**
     * Solta os lugares dos ingressos que deixaram de ocupar (desistência,
     * expiração, cancelamento).
     *
     * @param  iterable<int>  $ticketIds
     */
    public function release(iterable $ticketIds): void
    {
        $ids = collect($ticketIds)->map(fn ($id) => (int) $id)->all();

        if ($ids !== []) {
            TicketSeatClaim::whereIn('ticket_id', $ids)->delete();
        }
    }

    /**
     * O responsável troca de lugar (pelo painel): o novo lugar tem de ser do
     * mesmo tipo, ativo e livre no dia. Mantém tipo, lote, preço e código.
     */
    public function changeSeat(Ticket $ticket, TicketSeat $seat, User $user): Ticket
    {
        if (! in_array($ticket->status, [Ticket::STATUS_VALID, Ticket::STATUS_PENDING], true)) {
            throw new TicketException(TicketException::TICKET_NOT_VALID, 'Só ingresso válido ou aguardando pagamento troca de lugar.');
        }
        if (! $ticket->isSeat()) {
            throw new TicketException(TicketException::SEAT_WRONG_TYPE, 'Este ingresso não é de lugar marcado.');
        }
        if ($seat->id === $ticket->ticket_seat_id) {
            return $ticket;
        }

        $ticket->loadMissing('type');
        if (! $seat->soldBy($ticket->type)) {
            throw new TicketException(TicketException::SEAT_WRONG_TYPE, "O lugar {$seat->label} não é vendido pelo tipo {$ticket->type->name}.");
        }
        if (! $seat->active) {
            throw new TicketException(TicketException::SEAT_NOT_FOUND, "O lugar {$seat->label} está desativado.");
        }

        DB::transaction(function () use ($ticket, $seat, $user) {
            $event = TicketEvent::whereKey($ticket->ticket_event_id)->lockForUpdate()->firstOrFail();

            // Mesa cativa de outra matrícula: nem trocando de lugar.
            $captive = $seat->captiveFor($event);
            if ($captive !== null && ! TicketSeat::sameTitle($ticket->order->buyer_title, $captive)) {
                throw new TicketException(TicketException::SEAT_CAPTIVE, "O lugar {$seat->label} é mesa cativa da matrícula {$captive}: só um sócio desse título fica com ele. Tire a cativa em Mapas › Mesas cativas, se for o caso.");
            }

            $from = $ticket->seat?->label;
            $ticket->ticket_seat_id = $seat->id;
            $this->claim($ticket, $ticket->type->dayKey());
            $ticket->save();
            TicketOrderLog::record($ticket->ticket_order_id, TicketOrderLog::SEAT_CHANGED, $user, ['from' => $from, 'to' => $seat->label], $ticket);
        });

        return $ticket->fresh(['seat', 'type']);
    }

    /**
     * O mapa num dia, para o site e o painel: cada lugar com a situação, o
     * tipo que o vende naquele dia (e o lote à venda dele) e, para o painel,
     * o ingresso que o ocupa. No evento de vários dias, sem `$day` o mapa
     * sai sem tipo nem situação de venda (todos indisponíveis).
     *
     * Lugar indisponível: desativado, nenhum tipo ativo o vende no dia ou o
     * tipo não tem lote à venda agora. Mesa cativa ainda não liberada e não
     * ocupada: `captive`, salvo para quem é da matrícula (`$title`), que a vê
     * livre; `$panel` traz a matrícula e o nome em `captive`.
     *
     * @return array{types: list<array{type: TicketType, lot: ?\App\Models\Ticket\TicketLot, remaining: ?int}>, seats: list<array{seat: TicketSeat, status: string, type: ?TicketType, ticket: ?Ticket, captive: ?string}>}
     */
    public function layout(TicketMap $map, ?TicketEventDay $day = null, bool $includeInactiveTypes = false, ?string $title = null): array
    {
        $map->loadMissing('seats', 'event.days', 'event.types.lots', 'event.types.day', 'event.maps.seats');
        $event = $map->event;

        $types = $event->types
            ->filter(fn (TicketType $t) => $t->ticket_map_id === $map->id && ($t->active || $includeInactiveTypes))
            ->filter(fn (TicketType $t) => $event->hasDays() ? ($day !== null && $t->ticket_event_day_id === $day->id) : true)
            ->values();

        $summary = collect($this->inventory->summary($event))->keyBy(fn (array $row) => $row['type']->id);
        $occupants = $this->inventory->seatOccupants($event)[$day?->id ?? 0] ?? [];

        $seats = $map->seats->map(function (TicketSeat $seat) use ($types, $summary, $occupants, $event, $title) {
            $type = $types->first(fn (TicketType $t) => $seat->soldBy($t));
            $ticket = $occupants[$seat->id] ?? null;
            $captive = $seat->captiveFor($event);

            $status = match (true) {
                $ticket !== null => in_array($ticket->status, Ticket::SOLD, true) ? self::STATUS_SOLD : self::STATUS_HELD,
                ! $seat->active, $type === null, ! $type->active, ($summary[$type->id]['lot'] ?? null) === null => self::STATUS_UNAVAILABLE,
                $captive !== null && ! TicketSeat::sameTitle($title, $captive) => self::STATUS_CAPTIVE,
                default => self::STATUS_FREE,
            };

            return ['seat' => $seat, 'status' => $status, 'type' => $type, 'ticket' => $ticket, 'captive' => $captive];
        })->values()->all();

        return [
            'types' => $types->map(fn (TicketType $t) => [
                'type' => $t,
                'lot' => $summary[$t->id]['lot'] ?? null,
                'remaining' => $summary[$t->id]['remaining'] ?? null,
            ])->all(),
            'seats' => $seats,
        ];
    }

    /** Os lugares no texto da recusa: "M12, M13". */
    public function taken(array $seatIds): TicketException
    {
        $labels = TicketSeat::whereIn('id', $seatIds)->orderBy('label')->pluck('label')->implode(', ');

        return new TicketException(
            TicketException::SEAT_TAKEN,
            (count($seatIds) > 1 ? "Os lugares {$labels} acabaram" : "O lugar {$labels} acabou") . ' de ser reservado por outra pessoa. Escolha outro no mapa.',
            409,
            ['seat_ids' => array_values(array_map('intval', $seatIds))],
        );
    }
}

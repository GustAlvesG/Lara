<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketType;
use App\Models\Ticket\TicketOrder;
use App\Services\Signature\SignatureQrCode;
use App\Support\Cpf;
use App\Support\ImageSource;

/**
 * O JSON dos ingressos para o site (`/api/tickets/*`). Um lugar só, para a
 * lista, o detalhe e os pedidos saírem iguais.
 *
 * O código e o QR do ingresso só saem com o ingresso pago: o pendente ainda
 * não vale na entrada. O CPF do titular sai mascarado.
 *
 * Evento de vários dias: `days` no evento; no tipo e no ingresso, `validity`
 * (`event`, `day` = diária, `passport`) e o dia da diária.
 */
class TicketPresenter
{
    public function __construct(private TicketInventory $inventory, private SignatureQrCode $qr, private TicketSeatService $seats)
    {
    }

    public function event(TicketEvent $event, bool $detail = false): array
    {
        $event->loadMissing('days', 'types.day');
        $capacityByDay = $this->inventory->capacityLeftByDay($event);

        $data = [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'image_url' => ImageSource::url($event->image),
            'venue' => $event->venue,
            'status' => $event->status,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'sales_starts_at' => $event->sales_starts_at->toIso8601String(),
            'sales_ends_at' => $event->sales_ends_at->toIso8601String(),
            'sales_open' => $event->salesOpen(),
            'max_per_order' => $event->max_per_order,
            'capacity_left' => $capacityByDay === null ? null : (int) min($capacityByDay),
            'multi_day' => $event->hasDays(),
            'days' => $event->days->map(fn (TicketEventDay $day) => $this->day($day) + [
                'capacity_left' => $capacityByDay[$day->id] ?? null,
            ])->values(),
            'types' => collect($this->inventory->summary($event))
                ->filter(fn (array $row) => $row['type']->active)
                ->map(fn (array $row) => [
                    'id' => $row['type']->id,
                    'name' => $row['type']->name,
                    'description' => $row['type']->description,
                    'validity' => $row['type']->validity(),
                    'day_id' => $row['type']->ticket_event_day_id,
                    // Lugar marcado (mesa, bistrô…): escolhido no mapa `map_id`; não é entrada.
                    'kind' => $row['type']->kind(),
                    'map_id' => $row['type']->ticket_map_id,
                    'seat_category' => $row['type']->seat_category,
                    'seats_free' => $row['seats_free'],
                    'available' => $row['lot'] !== null,
                    'lot' => $row['lot'] ? $this->lot($row['lot']) + ['remaining' => $row['remaining']] : null,
                    'next_lot' => $row['next_lot'] ? $this->lot($row['next_lot']) : null,
                ])->values(),
        ];

        // Os mapas que algum tipo ativo usa (o desenho sai em `maps/{id}`).
        $mapIds = $event->types->where('active', true)->pluck('ticket_map_id')->filter()->unique();
        $event->loadMissing('maps');
        $data['has_map'] = $mapIds->isNotEmpty();
        $data['maps'] = $event->maps->whereIn('id', $mapIds->all())->map(fn (TicketMap $m) => ['id' => $m->id, 'name' => $m->name])->values();

        if ($detail) {
            $data['rules'] = $event->rules;
        }

        return $data;
    }

    /**
     * O mapa de lugares num dia: o desenho (área, fundo, áreas de orientação),
     * os tipos que vendem os lugares no dia, com o lote à venda, e cada lugar
     * com a situação (`free`, `held`, `sold`, `unavailable`) e o tipo que o
     * vende. No evento de vários dias, sem `$day` tudo sai indisponível.
     *
     * `$panel` (só o Lara): também os tipos inativos, o lugar desativado, e
     * de quem é cada lugar ocupado. O site nunca recebe quem comprou.
     */
    public function seatMap(TicketMap $map, ?TicketEventDay $day = null, bool $panel = false): array
    {
        $layout = $this->seats->layout($map, $day, includeInactiveTypes: $panel);

        return [
            'id' => $map->id,
            'event_id' => $map->ticket_event_id,
            'name' => $map->name,
            'width' => $map->width,
            'height' => $map->height,
            'background_url' => $map->backgroundUrl(),
            'day' => $day ? $this->day($day) : null,
            'refresh_after' => TicketSeatService::REFRESH_SECONDS,
            'areas' => array_values($map->areas ?? []),
            'types' => array_map(fn (array $row) => [
                'id' => $row['type']->id,
                'name' => $row['type']->name,
                'seat_category' => $row['type']->seat_category,
                'day_id' => $row['type']->ticket_event_day_id,
                'available' => $row['lot'] !== null,
                'lot' => $row['lot'] ? $this->lot($row['lot']) + ['remaining' => $row['remaining']] : null,
            ], $layout['types']),
            'seats' => array_map(function (array $row) use ($panel) {
                $seat = $row['seat'];
                $data = [
                    'id' => $seat->id,
                    'label' => $seat->label,
                    'category' => $seat->category,
                    'shape' => $seat->shape,
                    'x' => $seat->x,
                    'y' => $seat->y,
                    'w' => $seat->w,
                    'h' => $seat->h,
                    'places' => $seat->places,
                    'status' => $row['status'],
                    'type_id' => $row['type']?->id,
                ];

                if ($panel) {
                    $ticket = $row['ticket'];
                    $data += [
                        'active' => $seat->active,
                        'holder_name' => $ticket?->holder_name,
                        'order_id' => $ticket?->ticket_order_id,
                        'ticket_id' => $ticket?->id,
                    ];
                }

                return $data;
            }, $layout['seats']),
        ];
    }

    public function day(TicketEventDay $day): array
    {
        return [
            'id' => $day->id,
            'name' => $day->name,
            'label' => $day->label(),
            'starts_at' => $day->starts_at->toIso8601String(),
            'ends_at' => $day->ends_at->toIso8601String(),
        ];
    }

    public function lot(TicketLot $lot): array
    {
        return [
            'id' => $lot->id,
            'name' => $lot->name,
            'price_member' => (float) $lot->price_member,
            'price_non_member' => $lot->price_non_member === null ? null : (float) $lot->price_non_member,
            'members_only' => $lot->price_non_member === null,
            'starts_at' => $lot->starts_at?->toIso8601String(),
            'ends_at' => $lot->ends_at?->toIso8601String(),
        ];
    }

    /** O cupom conferido pelo site antes do pedido (o desconto final é o do pedido). */
    public function coupon(TicketCoupon $coupon): array
    {
        return [
            'code' => $coupon->code,
            'description' => $coupon->description,
            'kind' => $coupon->kind,
            'value' => (float) $coupon->value,
            'label' => $coupon->valueLabel(),
            'audience' => $coupon->audience,
            'type_ids' => $coupon->ticket_type_ids ? array_map('intval', $coupon->ticket_type_ids) : null,
            'ends_at' => $coupon->ends_at?->toIso8601String(),
        ];
    }

    public function order(TicketOrder $order, bool $withToken = false): array
    {
        $order->loadMissing('event.days', 'tickets.type.day', 'tickets.lot', 'tickets.seat.map', 'tickets.checkins', 'payment');

        $data = [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => TicketOrder::STATUS_LABELS[$order->status] ?? $order->status,
            'buyer_kind' => $order->buyer_kind,
            'buyer' => [
                'name' => $order->buyer_name,
                'email' => $order->buyer_email,
                'cpf' => Cpf::mask($order->buyer_cpf),
            ],
            'total' => (float) $order->total,
            'discount_total' => (float) $order->discount_total,
            'coupon_code' => $order->coupon_code,
            'courtesy' => (bool) $order->courtesy,
            'hold_until' => $order->status === TicketOrder::STATUS_PENDING_PAYMENT ? $order->hold_until?->toIso8601String() : null,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'origin' => $order->origin,
            'event' => [
                'id' => $order->event->id,
                'title' => $order->event->title,
                'venue' => $order->event->venue,
                'starts_at' => $order->event->starts_at->toIso8601String(),
                'ends_at' => $order->event->ends_at->toIso8601String(),
                'image_url' => ImageSource::url($order->event->image),
                'days' => $order->event->days->map(fn (TicketEventDay $day) => $this->day($day))->values(),
            ],
            'payment' => $order->payment ? [
                'id' => $order->payment->id,
                'manual' => (bool) $order->payment->is_manual,
                'status' => $order->payment->status,
                'paid_amount' => (float) $order->payment->paid_amount,
                'refunded_amount' => (float) $order->payment->refunded_amount,
            ] : null,
            'tickets' => $order->tickets->map(fn (Ticket $t) => $this->ticket($t))->values(),
        ];

        // O token só sai na criação do pedido do não sócio: o site guarda e
        // manda de volta no header `Ticket-Order-Token`.
        if ($withToken && $order->isGuest()) {
            $data['access_token'] = $order->access_token;
        }

        return $data;
    }

    public function ticket(Ticket $ticket): array
    {
        $sold = in_array($ticket->status, Ticket::SOLD, true);

        return [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'status_label' => Ticket::STATUS_LABELS[$ticket->status] ?? $ticket->status,
            'holder_name' => $ticket->holder_name,
            'holder_cpf' => Cpf::mask($ticket->holder_cpf),
            'type' => ['id' => $ticket->ticket_type_id, 'name' => $ticket->type?->name],
            'validity' => $ticket->type?->validity(),
            'day' => $ticket->type?->day ? $this->day($ticket->type->day) : null,
            'lot' => ['id' => $ticket->ticket_lot_id, 'name' => $ticket->lot?->name],
            // Lugar marcado: a mesa/bistrô/camarote; o ingresso não é entrada.
            'kind' => $ticket->isSeat() ? TicketType::KIND_SEAT : TicketType::KIND_ENTRY,
            'seat' => $ticket->seat ? [
                'id' => $ticket->seat->id,
                'label' => $ticket->seat->label,
                'category' => $ticket->seat->category,
                'places' => $ticket->seat->places,
                'map' => $ticket->seat->map ? ['id' => $ticket->seat->map->id, 'name' => $ticket->seat->map->name] : null,
            ] : null,
            'price_kind' => $ticket->price_kind,
            'list_price' => (float) $ticket->list_price,
            'discount_amount' => (float) $ticket->discount_amount,
            'amount' => (float) $ticket->amount,
            'code' => $sold ? $ticket->code : null,
            'qr_code' => $sold ? $this->qr->dataUri($ticket->code, 240) : null,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            // Cada entrada (o passaporte tem uma por dia).
            'entries' => $ticket->checkins->map(fn ($c) => [
                'day_id' => $c->ticket_event_day_id,
                'checked_in_at' => $c->checked_in_at->toIso8601String(),
            ])->values(),
        ];
    }
}

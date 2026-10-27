<?php

namespace App\Services\Ticket;

use App\Models\SchedulePayment;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketPayment;
use Illuminate\Support\Collection;

/**
 * Relatório financeiro dos ingressos (`ingressos.financeiro`).
 *
 * Conta por ingresso: vendidos = válidos + usados; o cancelado entra só com o
 * estorno dele (`tickets.refunded_amount`). Valor de face = preço do lote
 * (`list_price`); desconto = cupom; líquido = o que foi cobrado (`amount`).
 * Cortesia conta à parte (valor zero, face guardada). O recebido sai dos
 * pagamentos (eRede e baixa manual), menos os estornos.
 */
class TicketFinanceService
{
    /** Totais de um evento (ou de vários, para a lista). */
    public function totals(TicketEvent $event): array
    {
        $sold = $this->soldTickets($event);
        $payments = TicketPayment::whereHas('order', fn ($q) => $q->where('ticket_event_id', $event->id))->get();

        return [
            'sold' => $sold->count(),
            'used' => $sold->where('status', Ticket::STATUS_USED)->count(),
            'courtesies' => $sold->filter(fn (Ticket $t) => $t->order->courtesy)->count(),
            'face' => round((float) $sold->reject(fn (Ticket $t) => $t->order->courtesy)->sum('list_price'), 2),
            'discount' => round((float) $sold->sum('discount_amount'), 2),
            'net' => round((float) $sold->sum('amount'), 2),
            'paid' => round((float) $payments->sum('paid_amount'), 2),
            'refunded' => round((float) $payments->sum('refunded_amount'), 2),
            'received' => round((float) $payments->sum('paid_amount') - (float) $payments->sum('refunded_amount'), 2),
            'cancelled' => Ticket::where('ticket_event_id', $event->id)->where('status', Ticket::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Por tipo, lote e público (sócio / não sócio).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byLot(TicketEvent $event): Collection
    {
        return $this->soldTickets($event)
            ->groupBy(fn (Ticket $t) => $t->ticket_type_id . '|' . $t->ticket_lot_id . '|' . $t->price_kind)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'type' => $first->type?->fullName(),
                    'lot' => $first->lot?->name,
                    'price_kind' => TicketLot::PRICE_LABELS[$first->price_kind] ?? $first->price_kind,
                    'count' => $group->count(),
                    'courtesies' => $group->filter(fn (Ticket $t) => $t->order->courtesy)->count(),
                    'discount' => round((float) $group->sum('discount_amount'), 2),
                    'net' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->sortBy(['type', 'lot'])
            ->values();
    }

    /**
     * Recebido por forma de pagamento (eRede e cada forma de baixa manual).
     *
     * @return Collection<int, array{method: string, count: int, paid: float, refunded: float}>
     */
    public function byMethod(TicketEvent $event): Collection
    {
        return TicketPayment::whereHas('order', fn ($q) => $q->where('ticket_event_id', $event->id))
            ->get()
            ->groupBy(fn (TicketPayment $p) => $p->is_manual ? $p->payment_method : 'erede')
            ->map(fn (Collection $group, string $method) => [
                'method' => $method === 'erede' ? 'eRede (site)' : (SchedulePayment::MANUAL_METHODS[$method] ?? $method),
                'count' => $group->count(),
                'paid' => round((float) $group->sum('paid_amount'), 2),
                'refunded' => round((float) $group->sum('refunded_amount'), 2),
            ])
            ->values();
    }

    /**
     * Uso de cada cupom: ingressos com desconto vendidos e o total abatido.
     *
     * @return Collection<int, array{code: string, count: int, discount: float}>
     */
    public function byCoupon(TicketEvent $event): Collection
    {
        return $this->soldTickets($event)
            ->filter(fn (Ticket $t) => (float) $t->discount_amount > 0 && $t->order->coupon_code)
            ->groupBy(fn (Ticket $t) => $t->order->coupon_code)
            ->map(fn (Collection $group, string $code) => [
                'code' => $code,
                'count' => $group->count(),
                'discount' => round((float) $group->sum('discount_amount'), 2),
            ])
            ->sortBy('code')
            ->values();
    }

    /**
     * Linhas do CSV: um ingresso por linha (pagos, usados e cancelados).
     *
     * @return list<list<string>>
     */
    public function csvRows(TicketEvent $event): array
    {
        $money = fn ($v) => number_format((float) $v, 2, ',', '');

        $tickets = Ticket::with(['order.payment', 'type', 'lot'])
            ->where('ticket_event_id', $event->id)
            ->whereIn('status', [...Ticket::SOLD, Ticket::STATUS_CANCELLED])
            ->orderBy('ticket_order_id')
            ->orderBy('id')
            ->get();

        $rows = [[
            'Pedido', 'Pago em', 'Origem', 'Comprador', 'Tipo de comprador', 'Titular', 'CPF do titular', 'Ingresso', 'Lote', 'Preço',
            'Valor de face', 'Desconto', 'Cupom', 'Cobrado', 'Estornado', 'Pagamento', 'Cortesia', 'Situação', 'Entrada',
        ]];

        foreach ($tickets as $t) {
            $order = $t->order;
            $payment = $order->payment;
            $rows[] = [
                (string) $order->id,
                $order->paid_at?->format('d/m/Y H:i') ?? '',
                $order->origin === 'secretaria' ? 'Lara' : 'Site',
                $order->buyer_name,
                $order->isGuest() ? 'Não sócio' : 'Sócio',
                $t->holder_name,
                \App\Support\Cpf::mask($t->holder_cpf),
                (string) $t->type?->fullName(),
                (string) $t->lot?->name,
                TicketLot::PRICE_LABELS[$t->price_kind] ?? $t->price_kind,
                $money($t->list_price),
                $money($t->discount_amount),
                (string) $order->coupon_code,
                $money($t->amount),
                $money($t->refunded_amount),
                $payment ? ($payment->is_manual ? (SchedulePayment::MANUAL_METHODS[$payment->payment_method] ?? $payment->payment_method) : 'eRede') : '',
                $order->courtesy ? 'Sim' : 'Não',
                Ticket::STATUS_LABELS[$t->status] ?? $t->status,
                $t->checked_in_at?->format('d/m/Y H:i') ?? '',
            ];
        }

        return $rows;
    }

    /** @return Collection<int, Ticket> */
    private function soldTickets(TicketEvent $event): Collection
    {
        return Ticket::with(['order', 'type', 'lot'])
            ->where('ticket_event_id', $event->id)
            ->whereIn('status', Ticket::SOLD)
            ->get();
    }
}

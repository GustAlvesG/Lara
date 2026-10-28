<?php

namespace App\Services\Ticket;

use App\Models\SchedulePayment;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Relatório de caixa dos ingressos (docs/funcionalidades/ingressos.md): o que
 * cada pessoa recebeu no balcão, por dia e por forma de pagamento, para o
 * fechamento de caixa do financeiro. Junta todos os eventos.
 *
 *   - venda: baixa manual (`ticket_payments.is_manual`), no dia do `paid_at`,
 *     na conta de quem registrou (`recorded_by`) — a emissão com pagamento por
 *     fora e a baixa de pedido do site pago na secretaria;
 *   - cortesia: pedido de cortesia emitido no Lara, na conta de quem emitiu
 *     (`created_by`); conta ingressos, sem valor;
 *   - cancelamento: ingresso pago por baixa manual cancelado no período, na
 *     conta de quem cancelou. Só informa: a devolução, se houve, foi por fora
 *     do Lara (baixa manual não tem estorno), então não sai do total recebido;
 *   - site (eRede): só na visão de todos, num bloco à parte — não passa pelo
 *     caixa de ninguém.
 *
 * `$userId` preenchido = só o caixa dessa pessoa (quem não tem
 * `ingressos.caixa.todos` vê o próprio).
 */
class TicketCashService
{
    public const KIND_SALE = 'sale';
    public const KIND_COURTESY = 'courtesy';
    public const KIND_CANCEL = 'cancel';

    /** @var array<string, string> */
    public const KIND_LABELS = [
        self::KIND_SALE => 'Venda',
        self::KIND_COURTESY => 'Cortesia',
        self::KIND_CANCEL => 'Cancelamento',
    ];

    /** Período mais longo de uma vez (o relatório é de fechamento, não histórico). */
    public const MAX_DAYS = 62;

    /**
     * @return array{
     *   from: Carbon, to: Carbon, methods: array<string, string>,
     *   days: array<string, array<int, array{user: ?User, methods: array<string, float>, total: float, sales: int, tickets: int, courtesies: int, cancelled: int, cancelled_amount: float}>>,
     *   sellers: array<int, array{user: ?User, methods: array<string, float>, total: float, sales: int, tickets: int, courtesies: int, cancelled: int, cancelled_amount: float}>,
     *   totals: array{methods: array<string, float>, total: float, sales: int, tickets: int, courtesies: int, cancelled: int, cancelled_amount: float},
     *   entries: list<array<string, mixed>>,
     *   site: ?array{orders: int, tickets: int, amount: float, refunded: float}
     * }
     */
    public function report(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $entries = $this->sales($from, $to, $userId)
            ->concat($this->courtesies($from, $to, $userId))
            ->concat($this->cancellations($from, $to, $userId))
            ->sortBy(fn (array $e) => $e['at']->timestamp . '|' . $e['order_id'])
            ->values();

        $users = User::whereIn('id', $entries->pluck('user_id')->filter()->unique()->all())->get()->keyBy('id');
        $methods = $entries->where('kind', self::KIND_SALE)->pluck('method')->unique()
            ->sortBy(fn ($m) => array_search($m, array_keys(SchedulePayment::MANUAL_METHODS), true))
            ->mapWithKeys(fn ($m) => [$m => SchedulePayment::MANUAL_METHODS[$m] ?? $m])
            ->all();

        $days = [];
        $sellers = [];
        $totals = $this->blank($methods, null);
        foreach ($entries as $e) {
            $day = $e['at']->toDateString();
            $uid = (int) $e['user_id'];
            $days[$day][$uid] ??= $this->blank($methods, $users->get($uid));
            $sellers[$uid] ??= $this->blank($methods, $users->get($uid));
            $this->add($days[$day][$uid], $e);
            $this->add($sellers[$uid], $e);
            $this->add($totals, $e);
        }
        ksort($days);
        $byName = fn (array $a, array $b) => strcmp((string) $a['user']?->name, (string) $b['user']?->name);
        foreach ($days as &$rows) {
            uasort($rows, $byName);
        }
        unset($rows);
        uasort($sellers, $byName);

        return [
            'from' => $from,
            'to' => $to,
            'methods' => $methods,
            'days' => $days,
            'sellers' => $sellers,
            'totals' => $totals,
            'entries' => $entries->map(fn (array $e) => $e + ['user' => $users->get((int) $e['user_id'])])->all(),
            'site' => $userId === null ? $this->site($from, $to) : null,
        ];
    }

    /**
     * Linhas do CSV: um lançamento por linha.
     *
     * @return list<list<string>>
     */
    public function csvRows(array $report): array
    {
        $money = fn ($v) => number_format((float) $v, 2, ',', '');

        $rows = [['Data', 'Hora', 'Pessoa', 'Lançamento', 'Forma de pagamento', 'Evento', 'Pedido', 'Comprador', 'Ingressos', 'Valor']];
        foreach ($report['entries'] as $e) {
            $rows[] = [
                $e['at']->format('d/m/Y'),
                $e['at']->format('H:i'),
                (string) ($e['user']?->name ?? '—'),
                self::KIND_LABELS[$e['kind']],
                $e['kind'] === self::KIND_SALE ? (SchedulePayment::MANUAL_METHODS[$e['method']] ?? $e['method']) : '',
                (string) $e['event'],
                (string) $e['order_id'],
                (string) $e['buyer'],
                (string) $e['tickets'],
                $e['kind'] === self::KIND_COURTESY ? '0,00' : ($e['kind'] === self::KIND_CANCEL ? '-' : '') . $money($e['amount']),
            ];
        }

        return $rows;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sales(Carbon $from, Carbon $to, ?int $userId): Collection
    {
        return TicketPayment::with(['order' => fn ($q) => $q->withCount('tickets')->with('event')])
            ->where('is_manual', true)
            ->whereBetween('paid_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('recorded_by', $userId))
            ->get()
            ->map(fn (TicketPayment $p) => [
                'kind' => self::KIND_SALE,
                'at' => $p->paid_at,
                'user_id' => $p->recorded_by,
                'method' => $p->payment_method,
                'amount' => round((float) $p->paid_amount, 2),
                'tickets' => (int) $p->order?->tickets_count,
                'order_id' => $p->ticket_order_id,
                'event' => $p->order?->event?->title,
                'buyer' => $p->order?->buyer_name,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function courtesies(Carbon $from, Carbon $to, ?int $userId): Collection
    {
        return TicketOrder::with('event')->withCount('tickets')
            ->where('courtesy', true)
            ->where('origin', TicketOrder::ORIGIN_COUNTER)
            ->whereBetween('paid_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->get()
            ->map(fn (TicketOrder $o) => [
                'kind' => self::KIND_COURTESY,
                'at' => $o->paid_at,
                'user_id' => $o->created_by,
                'method' => null,
                'amount' => 0.0,
                'tickets' => (int) $o->tickets_count,
                'order_id' => $o->id,
                'event' => $o->event?->title,
                'buyer' => $o->buyer_name,
            ]);
    }

    /** Ingresso pago por baixa manual e cancelado no período: um lançamento por pedido e pessoa. */
    private function cancellations(Carbon $from, Carbon $to, ?int $userId): Collection
    {
        return Ticket::with('order.event')
            ->where('status', Ticket::STATUS_CANCELLED)
            ->whereBetween('cancelled_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('cancelled_by', $userId))
            ->whereHas('order.payment', fn ($q) => $q->where('is_manual', true))
            ->get()
            ->groupBy(fn (Ticket $t) => $t->ticket_order_id . '|' . $t->cancelled_by . '|' . $t->cancelled_at->toDateString())
            ->map(fn (Collection $group) => [
                'kind' => self::KIND_CANCEL,
                'at' => $group->max('cancelled_at'),
                'user_id' => $group->first()->cancelled_by,
                'method' => null,
                'amount' => round((float) $group->sum('amount'), 2),
                'tickets' => $group->count(),
                'order_id' => $group->first()->ticket_order_id,
                'event' => $group->first()->order?->event?->title,
                'buyer' => $group->first()->order?->buyer_name,
            ])
            ->values();
    }

    /** @return array{orders: int, tickets: int, amount: float, refunded: float} */
    private function site(Carbon $from, Carbon $to): array
    {
        $payments = TicketPayment::with(['order' => fn ($q) => $q->withCount('tickets')])
            ->where('is_manual', false)
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        return [
            'orders' => $payments->count(),
            'tickets' => (int) $payments->sum(fn (TicketPayment $p) => $p->order?->tickets_count),
            'amount' => round((float) $payments->sum('paid_amount'), 2),
            'refunded' => round((float) $payments->sum('refunded_amount'), 2),
        ];
    }

    private function blank(array $methods, ?User $user): array
    {
        return [
            'user' => $user,
            'methods' => array_fill_keys(array_keys($methods), 0.0),
            'total' => 0.0,
            'sales' => 0,
            'tickets' => 0,
            'courtesies' => 0,
            'cancelled' => 0,
            'cancelled_amount' => 0.0,
        ];
    }

    private function add(array &$row, array $e): void
    {
        match ($e['kind']) {
            self::KIND_SALE => (function () use (&$row, $e) {
                $row['methods'][$e['method']] = round(($row['methods'][$e['method']] ?? 0) + $e['amount'], 2);
                $row['total'] = round($row['total'] + $e['amount'], 2);
                $row['sales']++;
                $row['tickets'] += $e['tickets'];
            })(),
            self::KIND_COURTESY => $row['courtesies'] += $e['tickets'],
            self::KIND_CANCEL => (function () use (&$row, $e) {
                $row['cancelled'] += $e['tickets'];
                $row['cancelled_amount'] = round($row['cancelled_amount'] + $e['amount'], 2);
            })(),
        };
    }
}

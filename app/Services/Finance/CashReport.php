<?php

namespace App\Services\Finance;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financeiro › Caixa (docs/funcionalidades/financeiro.md): o que cada pessoa
 * recebeu no balcão, por dia e por forma de pagamento, para o fechamento de
 * caixa. Era o caixa dos ingressos; desde 13/11/2026 junta as três origens.
 *
 *   - venda (recebimento): baixa manual de Reservas, Torneios ou Ingressos,
 *     no dia do `paid_at`, na conta de quem registrou (`recorded_by`);
 *   - cortesia (só Ingressos): pedido de cortesia emitido no Lara, na conta
 *     de quem emitiu; conta ingressos, sem valor;
 *   - cancelamento (só Ingressos): ingresso pago por baixa manual cancelado
 *     no período, na conta de quem cancelou. Só informa: a devolução, se
 *     houve, foi por fora do Lara, então não sai do total recebido;
 *   - site (eRede): só na visão de todos, à parte e por origem — não passa
 *     pelo caixa de ninguém.
 *
 * `$userId` preenchido = só o caixa dessa pessoa (quem não tem
 * `ingressos.caixa.todos` vê o próprio).
 */
class CashReport
{
    public const KIND_SALE = 'sale';
    public const KIND_COURTESY = 'courtesy';
    public const KIND_CANCEL = 'cancel';

    /** @var array<string, string> */
    public const KIND_LABELS = [
        self::KIND_SALE => 'Recebimento',
        self::KIND_COURTESY => 'Cortesia',
        self::KIND_CANCEL => 'Cancelamento',
    ];

    /** Período mais longo de uma vez (o relatório é de fechamento, não histórico). */
    public const MAX_DAYS = 62;

    public function __construct(private FinanceLedger $ledger)
    {
    }

    /**
     * @return array{
     *   from: Carbon, to: Carbon, methods: array<string, string>,
     *   days: array<string, array<int, array>>, sellers: array<int, array>, totals: array,
     *   entries: list<array<string, mixed>>,
     *   site: ?array<string, array{count: int, amount: float, refunded: float}>
     * }
     */
    public function report(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $entries = $this->sales($from, $to, $userId)
            ->concat($this->courtesies($from, $to, $userId))
            ->concat($this->cancellations($from, $to, $userId))
            ->sortBy(fn (array $e) => $e['at']->timestamp . '|' . $e['origin'] . '|' . $e['ref'])
            ->values();

        $users = User::whereIn('id', $entries->pluck('user_id')->filter()->unique()->all())->get()->keyBy('id');
        $methods = $entries->where('kind', self::KIND_SALE)->pluck('method')->unique()
            ->sortBy(fn ($m) => array_search($m, array_keys(PaymentMethods::MANUAL), true))
            ->mapWithKeys(fn ($m) => [$m => PaymentMethods::label($m)])
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

        $rows = [['Data', 'Hora', 'Pessoa', 'Origem', 'Lançamento', 'Forma de pagamento', 'Referência', 'Código', 'Pagador', 'Ingressos', 'Valor']];
        foreach ($report['entries'] as $e) {
            $rows[] = [
                $e['at']->format('d/m/Y'),
                $e['at']->format('H:i'),
                (string) ($e['user']?->name ?? '—'),
                FinanceLedger::ORIGINS[$e['origin']] ?? $e['origin'],
                self::KIND_LABELS[$e['kind']],
                $e['kind'] === self::KIND_SALE ? PaymentMethods::label($e['method']) : '',
                (string) $e['reference'],
                (string) $e['ref'],
                (string) $e['payer'],
                $e['tickets'] === null ? '' : (string) $e['tickets'],
                $e['kind'] === self::KIND_COURTESY ? '0,00' : ($e['kind'] === self::KIND_CANCEL ? '-' : '') . $money($e['amount']),
            ];
        }

        return $rows;
    }

    /** Baixas manuais das três origens. */
    private function sales(Carbon $from, Carbon $to, ?int $userId): Collection
    {
        $rows = $this->ledger->query([
            'from' => $from,
            'to' => $to,
            'method' => FinanceLedger::METHOD_MANUAL,
            'recorded_by' => $userId,
        ])->get();

        // Ingressos por pedido, para o "vendas · ingressos" do balcão.
        $orderIds = $rows->where('origin', FinanceLedger::ORIGIN_INGRESSOS)->pluck('reference_id')->unique()->all();
        $tickets = $orderIds === [] ? collect() : DB::table('tickets')
            ->whereIn('ticket_order_id', $orderIds)
            ->groupBy('ticket_order_id')
            ->selectRaw('ticket_order_id, COUNT(*) as qty')
            ->pluck('qty', 'ticket_order_id');

        return $rows->map(fn (object $r) => [
            'kind' => self::KIND_SALE,
            'origin' => $r->origin,
            'at' => Carbon::parse($r->paid_at),
            'user_id' => $r->recorded_by,
            'method' => $r->payment_method,
            'amount' => round((float) $r->paid_amount, 2),
            'tickets' => $r->origin === FinanceLedger::ORIGIN_INGRESSOS ? (int) ($tickets[$r->reference_id] ?? 0) : null,
            'ref' => (int) $r->reference_id,
            'reference' => $r->reference,
            'payer' => $r->payer_name,
        ]);
    }

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
                'origin' => FinanceLedger::ORIGIN_INGRESSOS,
                'at' => $o->paid_at,
                'user_id' => $o->created_by,
                'method' => null,
                'amount' => 0.0,
                'tickets' => (int) $o->tickets_count,
                'ref' => $o->id,
                'reference' => $o->event?->title,
                'payer' => $o->buyer_name,
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
                'origin' => FinanceLedger::ORIGIN_INGRESSOS,
                'at' => $group->max('cancelled_at'),
                'user_id' => $group->first()->cancelled_by,
                'method' => null,
                'amount' => round((float) $group->sum('amount'), 2),
                'tickets' => $group->count(),
                'ref' => $group->first()->ticket_order_id,
                'reference' => $group->first()->order?->event?->title,
                'payer' => $group->first()->order?->buyer_name,
            ])
            ->values();
    }

    /**
     * O que entrou pelo site (eRede) no período, por origem.
     *
     * @return array<string, array{count: int, amount: float, refunded: float}>
     */
    private function site(Carbon $from, Carbon $to): array
    {
        $summary = $this->ledger->summary(['from' => $from, 'to' => $to, 'method' => FinanceLedger::METHOD_ONLINE]);

        return array_map(fn (array $o) => [
            'count' => $o['count'],
            'amount' => $o['paid'],
            'refunded' => $o['refunded'],
        ], $summary['origins']);
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
                $row['tickets'] += (int) $e['tickets'];
            })(),
            self::KIND_COURTESY => $row['courtesies'] += $e['tickets'],
            self::KIND_CANCEL => (function () use (&$row, $e) {
                $row['cancelled'] += $e['tickets'];
                $row['cancelled_amount'] = round($row['cancelled_amount'] + $e['amount'], 2);
            })(),
        };
    }
}

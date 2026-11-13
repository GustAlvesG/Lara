<?php

namespace App\Services\Finance;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Todos os recebimentos do Lara numa lista só (módulo Financeiro,
 * docs/funcionalidades/financeiro.md): Reservas (`schedule_payments`),
 * Torneios (`tournament_payments`) e Ingressos (`ticket_payments`).
 *
 * As três tabelas continuam separadas (decisão de 13/11/2026: tabela única
 * fica para depois). Aqui elas viram uma consulta UNION com as mesmas
 * colunas, e é dela que saem a lista de Recebimentos, o CSV, a Visão geral
 * e o Caixa:
 *
 *   origin, id, paid_at, payment_method, is_manual, paid_amount,
 *   refunded_amount, recorded_by, tid, payer_name, payer_cpf, reference,
 *   reference_id
 *
 * - `reference`: o que foi pago (grupo do local, título do torneio, evento);
 * - `reference_id`: para o link de detalhe — o próprio pagamento (Reservas),
 *   o torneio (Torneios) ou o pedido (Ingressos).
 *
 * Situação, como nas telas de cada módulo: estornado = estorno ≥ pago;
 * estornado em parte = algum estorno; pago = sem estorno.
 */
class FinanceLedger
{
    public const ORIGIN_RESERVAS = 'reservas';
    public const ORIGIN_TORNEIOS = 'torneios';
    public const ORIGIN_INGRESSOS = 'ingressos';

    public const ORIGINS = [
        self::ORIGIN_RESERVAS => 'Reservas',
        self::ORIGIN_TORNEIOS => 'Torneios',
        self::ORIGIN_INGRESSOS => 'Ingressos',
    ];

    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        self::STATUS_PAID => 'Pago',
        self::STATUS_PARTIAL => 'Estornado em parte',
        self::STATUS_REFUNDED => 'Estornado',
    ];

    /** Filtro de forma: além de cada forma, "toda baixa manual" e "todo pagamento pela eRede". */
    public const METHOD_MANUAL = 'manual';
    public const METHOD_ONLINE = 'online';

    /**
     * A consulta já filtrada, mais nova primeiro.
     *
     * @param  array{from?: ?Carbon, to?: ?Carbon, origin?: ?string, method?: ?string, status?: ?string, search?: ?string, recorded_by?: ?int}  $filters
     */
    public function query(array $filters = []): Builder
    {
        $query = DB::query()->fromSub($this->union(), 'ledger');

        if (! empty($filters['from'])) {
            $query->where('paid_at', '>=', $filters['from']->copy()->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('paid_at', '<=', $filters['to']->copy()->endOfDay());
        }

        if (isset(self::ORIGINS[$filters['origin'] ?? ''])) {
            $query->where('origin', $filters['origin']);
        }

        $method = $filters['method'] ?? null;
        if ($method === self::METHOD_MANUAL) {
            $query->where('is_manual', true);
        } elseif ($method === self::METHOD_ONLINE) {
            $query->where('is_manual', false);
        } elseif ($method) {
            $query->where('payment_method', $method);
        }

        match ($filters['status'] ?? null) {
            self::STATUS_PAID => $query->where('refunded_amount', '<=', 0),
            self::STATUS_PARTIAL => $query->where('refunded_amount', '>', 0)->whereColumn('refunded_amount', '<', 'paid_amount'),
            self::STATUS_REFUNDED => $query->where('refunded_amount', '>', 0)->whereColumn('refunded_amount', '>=', 'paid_amount'),
            default => null,
        };

        if (! empty($filters['recorded_by'])) {
            $query->where('recorded_by', (int) $filters['recorded_by']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search);
            $query->where(function (Builder $q) use ($search, $digits) {
                $q->where('payer_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('tid', $search);
                if ($digits !== '') {
                    $q->orWhere('payer_cpf', 'like', "%{$digits}%");
                }
            });
        }

        return $query->orderByDesc('paid_at')->orderBy('origin')->orderByDesc('id');
    }

    /**
     * Totais da consulta filtrada: geral, por origem e por forma, separando
     * eRede de baixa manual.
     *
     * @return array{
     *   total: array{count: int, paid: float, refunded: float, net: float, online: float, manual: float},
     *   origins: array<string, array{count: int, paid: float, refunded: float, net: float, online: float, manual: float}>,
     *   methods: array<string, array{label: string, manual: bool, count: int, paid: float, refunded: float, net: float}>
     * }
     */
    public function summary(array $filters = []): array
    {
        $rows = DB::query()
            ->fromSub($this->query($filters)->reorder(), 'filtered')
            ->selectRaw('origin, payment_method, is_manual, COUNT(*) as qty, SUM(paid_amount) as paid, SUM(refunded_amount) as refunded')
            ->groupBy('origin', 'payment_method', 'is_manual')
            ->get();

        $blank = ['count' => 0, 'paid' => 0.0, 'refunded' => 0.0, 'net' => 0.0, 'online' => 0.0, 'manual' => 0.0];
        $total = $blank;
        $origins = array_map(fn () => $blank, self::ORIGINS);
        $methods = [];

        foreach ($rows as $row) {
            $paid = round((float) $row->paid, 2);
            $refunded = round((float) $row->refunded, 2);
            $manual = (bool) $row->is_manual;

            foreach ([&$total, &$origins[$row->origin]] as &$bucket) {
                $bucket['count'] += (int) $row->qty;
                $bucket['paid'] = round($bucket['paid'] + $paid, 2);
                $bucket['refunded'] = round($bucket['refunded'] + $refunded, 2);
                $bucket['net'] = round($bucket['paid'] - $bucket['refunded'], 2);
                $bucket[$manual ? 'manual' : 'online'] = round($bucket[$manual ? 'manual' : 'online'] + $paid, 2);
            }
            unset($bucket);

            $method = (string) $row->payment_method;
            $methods[$method] ??= ['label' => PaymentMethods::label($method), 'manual' => $manual, 'count' => 0, 'paid' => 0.0, 'refunded' => 0.0, 'net' => 0.0];
            $methods[$method]['count'] += (int) $row->qty;
            $methods[$method]['paid'] = round($methods[$method]['paid'] + $paid, 2);
            $methods[$method]['refunded'] = round($methods[$method]['refunded'] + $refunded, 2);
            $methods[$method]['net'] = round($methods[$method]['paid'] - $methods[$method]['refunded'], 2);
        }

        uasort($methods, fn ($a, $b) => $b['paid'] <=> $a['paid']);

        return ['total' => $total, 'origins' => $origins, 'methods' => $methods];
    }

    /** A situação de uma linha da consulta. */
    public static function status(object $row): string
    {
        $refunded = (float) $row->refunded_amount;

        if ($refunded <= 0) {
            return self::STATUS_PAID;
        }

        return $refunded >= (float) $row->paid_amount ? self::STATUS_REFUNDED : self::STATUS_PARTIAL;
    }

    /** As três tabelas com as mesmas colunas. */
    private function union(): Builder
    {
        // Pagador e referência por subconsulta, e não por join: um pagamento
        // de Reservas cobre vários horários e um de Torneios várias
        // inscrições; o join multiplicaria a linha (e a soma).
        $schedulePayer = fn (string $column) => DB::table('schedules')
            ->join('members', 'members.id', '=', 'schedules.member_id')
            ->whereColumn('schedules.schedule_payment_id', 'schedule_payments.id')
            ->orderBy('schedules.id')
            ->limit(1)
            ->select("members.{$column}");

        $scheduleGroup = DB::table('schedules')
            ->join('places', 'places.id', '=', 'schedules.place_id')
            ->join('place_groups', 'place_groups.id', '=', 'places.place_group_id')
            ->whereColumn('schedules.schedule_payment_id', 'schedule_payments.id')
            ->orderBy('schedules.id')
            ->limit(1)
            ->select('place_groups.name');

        $reservas = DB::table('schedule_payments')
            ->whereNotNull('schedule_payments.paid_at')
            ->select([
                DB::raw("'" . self::ORIGIN_RESERVAS . "' as origin"),
                'schedule_payments.id',
                'schedule_payments.paid_at',
                'schedule_payments.payment_method',
                'schedule_payments.is_manual',
                'schedule_payments.paid_amount',
                DB::raw('COALESCE(schedule_payments.refunded_amount, 0) as refunded_amount'),
                'schedule_payments.recorded_by',
                'schedule_payments.payment_integration_id as tid',
            ])
            ->selectSub($schedulePayer('name'), 'payer_name')
            ->selectSub($schedulePayer('cpf'), 'payer_cpf')
            ->selectSub($scheduleGroup, 'reference')
            ->addSelect('schedule_payments.id as reference_id');

        $tournamentOf = fn (string $column) => DB::table('tournament_registrations')
            ->join('tournament_categories', 'tournament_categories.id', '=', 'tournament_registrations.tournament_category_id')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_categories.tournament_id')
            ->whereColumn('tournament_registrations.tournament_payment_id', 'tournament_payments.id')
            ->orderBy('tournament_registrations.id')
            ->limit(1)
            ->select("tournaments.{$column}");

        $torneios = DB::table('tournament_payments')
            ->leftJoin('members', 'members.id', '=', 'tournament_payments.member_id')
            ->select([
                DB::raw("'" . self::ORIGIN_TORNEIOS . "' as origin"),
                'tournament_payments.id',
                'tournament_payments.paid_at',
                'tournament_payments.payment_method',
                'tournament_payments.is_manual',
                'tournament_payments.paid_amount',
                DB::raw('COALESCE(tournament_payments.refunded_amount, 0) as refunded_amount'),
                'tournament_payments.recorded_by',
                'tournament_payments.payment_integration_id as tid',
                'members.name as payer_name',
                'members.cpf as payer_cpf',
            ])
            ->selectSub($tournamentOf('title'), 'reference')
            ->selectSub($tournamentOf('id'), 'reference_id');

        $ingressos = DB::table('ticket_payments')
            ->join('ticket_orders', 'ticket_orders.id', '=', 'ticket_payments.ticket_order_id')
            ->leftJoin('ticket_events', 'ticket_events.id', '=', 'ticket_orders.ticket_event_id')
            ->select([
                DB::raw("'" . self::ORIGIN_INGRESSOS . "' as origin"),
                'ticket_payments.id',
                'ticket_payments.paid_at',
                'ticket_payments.payment_method',
                'ticket_payments.is_manual',
                'ticket_payments.paid_amount',
                DB::raw('COALESCE(ticket_payments.refunded_amount, 0) as refunded_amount'),
                'ticket_payments.recorded_by',
                'ticket_payments.payment_integration_id as tid',
                'ticket_orders.buyer_name as payer_name',
                'ticket_orders.buyer_cpf as payer_cpf',
                'ticket_events.title as reference',
                'ticket_payments.ticket_order_id as reference_id',
            ]);

        return $reservas->unionAll($torneios)->unionAll($ingressos);
    }
}

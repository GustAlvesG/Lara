<?php

namespace App\Services\MultiVendas;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Painel Vendas (MultiVendas) dos dashboards do site — o Grafana "Vendas
 * Hoje" levado para o site, com período de/até e filtro de lojas que vale
 * para todos os gráficos (no Grafana, Vendedores ignorava o filtro; aqui não).
 *
 * Os números saem da view `vw_DashboardVendas` (SalesDashboardRepository).
 * Aqui ficam a validação do filtro, o cache (o servidor do MultiVendas é o do
 * caixa: o painel não pode martelar a view) e a forma da resposta. Os
 * agrupamentos e os nomes de "não informado" são feitos em PHP para o teste
 * alcançar sem SQL Server.
 *
 * Os vendedores aparecem pelo nome: o painel é só do sócio administrador.
 */
class SalesDashboardService
{
    /** Teto do período, em dias. */
    public const MAX_DAYS = 366;

    /** Quantos produtos no "mais vendidos". */
    public const TOP_PRODUCTS = 10;

    /** Cache do período que inclui hoje (os números mudam a cada venda). */
    public const CACHE_TODAY_SECONDS = 60;

    /** Cache do período todo no passado, e da lista de lojas. */
    public const CACHE_PAST_SECONDS = 600;

    /**
     * Lojas que viram uma barra só no gráfico por loja, como no Grafana
     * (`Loja LIKE '%Gastrobar%'`). O filtro continua por loja de verdade.
     *
     * @var array<string, string> trecho do nome => grupo
     */
    public const STORE_GROUPS = ['gastrobar' => 'Gastrobar'];

    public function __construct(private SalesDashboardRepository $repository)
    {
    }

    /** @return list<string> */
    public function stores(): array
    {
        return Cache::remember('dashboard:vendas:lojas', self::CACHE_PAST_SECONDS, fn () => $this->guard(fn () => $this->repository->stores()));
    }

    /**
     * @param  array<string, mixed>  $input  from, to (Y-m-d), stores (lista de nomes)
     */
    public function filter(array $input): SalesFilter
    {
        $today = now()->startOfDay();
        $from = $this->date($input['from'] ?? null, 'from') ?? $today->copy();
        // Sem "até": hoje (ou o próprio "de", se ele estiver à frente).
        $to = $this->date($input['to'] ?? null, 'to') ?? ($from->gt($today) ? $from->copy() : $today->copy());

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'A data final não pode ser antes da inicial.']);
        }
        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'O período pode ter no máximo ' . self::MAX_DAYS . ' dias.']);
        }

        $stores = $input['stores'] ?? [];
        if (is_string($stores)) {
            $stores = $stores === '' ? [] : explode(',', $stores);
        }
        if (! is_array($stores)) {
            throw ValidationException::withMessages(['stores' => 'Lojas inválidas.']);
        }
        $stores = array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), $stores), fn ($s) => $s !== '')));

        if ($stores !== []) {
            $unknown = array_diff($stores, $this->stores());
            if ($unknown !== []) {
                throw ValidationException::withMessages(['stores' => 'Loja desconhecida: ' . implode(', ', $unknown) . '.']);
            }
            sort($stores);
        }

        return new SalesFilter($from, $to, $stores);
    }

    public function sales(SalesFilter $f): array
    {
        $ttl = $f->to->gte(now()->startOfDay()) ? self::CACHE_TODAY_SECONDS : self::CACHE_PAST_SECONDS;

        return Cache::remember($f->cacheKey(), $ttl, fn () => $this->guard(fn () => $this->build($f)));
    }

    private function build(SalesFilter $f): array
    {
        $totals = $this->repository->totals($f);
        $total = round((float) ($totals->total ?? 0), 2);
        $count = (int) ($totals->sales ?? 0);

        return [
            'period' => ['from' => $f->from->toDateString(), 'to' => $f->to->toDateString(), 'days' => $f->days()],
            'stores' => ['selected' => $f->stores, 'all' => $f->stores === []],
            'kpis' => [
                ['key' => 'net', 'label' => 'Faturamento', 'value' => $total, 'format' => 'money'],
                ['key' => 'average_ticket', 'label' => 'Ticket médio', 'value' => $count > 0 ? round($total / $count, 2) : 0.0, 'format' => 'money'],
                ['key' => 'sales', 'label' => 'Vendas', 'value' => $count, 'format' => 'int'],
            ],
            'by_store' => $this->ranked($this->repository->byStore($f), 'Sem loja', fn (string $label) => $this->storeGroup($label)),
            'top_products' => $this->ranked($this->repository->topProducts($f, self::TOP_PRODUCTS), 'Sem nome'),
            'by_hour' => $this->hours($this->repository->byHour($f)),
            'by_day' => $this->days($f, $this->repository->byDay($f)),
            'payment_methods' => $this->ranked($this->repository->paymentMethods($f), 'Não informado'),
            'sellers' => $this->ranked($this->repository->sellers($f), 'Não Identificado'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Soma por rótulo (juntando os que `$group` une), do maior para o menor.
     *
     * @return list<array{label: string, amount: float}>
     */
    private function ranked(array $rows, string $empty, ?callable $group = null): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row->label ?? ''));
            $label = $label === '' ? $empty : $label;
            $label = $group ? $group($label) : $label;
            $sums[$label] = ($sums[$label] ?? 0) + (float) $row->amount;
        }
        arsort($sums);

        $out = [];
        foreach ($sums as $label => $amount) {
            $out[] = ['label' => (string) $label, 'amount' => round($amount, 2)];
        }

        return $out;
    }

    private function storeGroup(string $store): string
    {
        foreach (self::STORE_GROUPS as $needle => $group) {
            if (str_contains(mb_strtolower($store), $needle)) {
                return $group;
            }
        }

        return $store;
    }

    /**
     * Da primeira à última hora com venda, sem buraco (hora sem venda = 0).
     *
     * @return list<array{hour: int, amount: float}>
     */
    private function hours(array $rows): array
    {
        $byHour = [];
        foreach ($rows as $row) {
            $byHour[(int) $row->hour] = ($byHour[(int) $row->hour] ?? 0) + (float) $row->amount;
        }
        if ($byHour === []) {
            return [];
        }

        $out = [];
        for ($h = min(array_keys($byHour)); $h <= max(array_keys($byHour)); $h++) {
            $out[] = ['hour' => $h, 'amount' => round($byHour[$h] ?? 0, 2)];
        }

        return $out;
    }

    /**
     * Todos os dias do período, com zero onde não houve venda.
     *
     * @return list<array{date: string, amount: float, sales: int}>
     */
    private function days(SalesFilter $f, array $rows): array
    {
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[Carbon::parse($row->day)->toDateString()] = $row;
        }

        $out = [];
        for ($d = $f->from->copy(); $d->lte($f->to); $d->addDay()) {
            $row = $byDay[$d->toDateString()] ?? null;
            $out[] = [
                'date' => $d->toDateString(),
                'amount' => round((float) ($row->amount ?? 0), 2),
                'sales' => (int) ($row->sales ?? 0),
            ];
        }

        return $out;
    }

    private function date(mixed $value, string $field): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Data inválida (use AAAA-MM-DD).']);
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $date = false;
        }
        if (! $date || $date->toDateString() !== $value) {
            throw ValidationException::withMessages([$field => 'Data inválida (use AAAA-MM-DD).']);
        }

        return $date;
    }

    /**
     * O servidor do caixa fora do ar não pode virar 500 nem vazar a mensagem
     * do driver (traz host e usuário): registra e devolve o erro do painel.
     */
    private function guard(callable $run): mixed
    {
        try {
            return $run();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            throw new SalesDashboardUnavailable('O MultiVendas não respondeu. Tente de novo em instantes.', 0, $e);
        }
    }
}

<?php

namespace App\Services\MultiVendas;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Leitura da view `vw_DashboardVendas` (servidor do MultiVendas, banco
 * FUNCSIDERURG de lá) para o painel Vendas dos dashboards do site — as mesmas
 * consultas do Grafana "Vendas Hoje", com período de/até no lugar de "hoje".
 *
 * Só SELECT, sempre pelo query builder: período e lojas vão como bindings,
 * nunca no texto do SQL. Cada `query*()` só monta a consulta (o teste confere
 * o SQL sem conectar); o método sem o prefixo executa.
 *
 * Colunas usadas da view: Data_Venda (date), Data_Hora_Venda (datetime),
 * ID_Venda, Loja, Produto, Valor_Liquido, Metodos_Pagamento, Vendedor. Cada
 * linha da view é um item vendido: venda = ID_Venda distinto.
 */
class SalesDashboardRepository
{
    public const CONNECTION = 'vendas_sqlsrv';

    public const VIEW = 'vw_DashboardVendas';

    /** @return list<string> */
    public function stores(): array
    {
        return $this->queryStores()->pluck('Loja')->map(fn ($s) => (string) $s)->all();
    }

    public function queryStores(): Builder
    {
        return $this->view()->select('Loja')->distinct()->whereNotNull('Loja')->orderBy('Loja');
    }

    /** @return object{total: mixed, sales: mixed} */
    public function totals(SalesFilter $f): object
    {
        return $this->queryTotals($f)->first() ?? (object) ['total' => 0, 'sales' => 0];
    }

    public function queryTotals(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('SUM(Valor_Liquido) AS total, COUNT(DISTINCT ID_Venda) AS sales');
    }

    public function byStore(SalesFilter $f): array
    {
        return $this->queryByStore($f)->get()->all();
    }

    public function queryByStore(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('Loja AS label, SUM(Valor_Liquido) AS amount')
            ->groupBy('Loja');
    }

    public function topProducts(SalesFilter $f, int $limit = 10): array
    {
        return $this->queryTopProducts($f, $limit)->get()->all();
    }

    public function queryTopProducts(SalesFilter $f, int $limit = 10): Builder
    {
        return $this->filtered($f)->selectRaw('Produto AS label, SUM(Valor_Liquido) AS amount')
            ->groupBy('Produto')
            ->orderByRaw('SUM(Valor_Liquido) DESC')
            ->limit($limit);
    }

    public function byHour(SalesFilter $f): array
    {
        return $this->queryByHour($f)->get()->all();
    }

    public function queryByHour(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('DATEPART(HOUR, Data_Hora_Venda) AS hour, SUM(Valor_Liquido) AS amount')
            ->groupByRaw('DATEPART(HOUR, Data_Hora_Venda)');
    }

    public function byDay(SalesFilter $f): array
    {
        return $this->queryByDay($f)->get()->all();
    }

    public function queryByDay(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('Data_Venda AS day, SUM(Valor_Liquido) AS amount, COUNT(DISTINCT ID_Venda) AS sales')
            ->groupBy('Data_Venda');
    }

    public function paymentMethods(SalesFilter $f): array
    {
        return $this->queryPaymentMethods($f)->get()->all();
    }

    public function queryPaymentMethods(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('Metodos_Pagamento AS label, SUM(Valor_Liquido) AS amount')
            ->groupBy('Metodos_Pagamento');
    }

    public function sellers(SalesFilter $f): array
    {
        return $this->querySellers($f)->get()->all();
    }

    public function querySellers(SalesFilter $f): Builder
    {
        return $this->filtered($f)->selectRaw('Vendedor AS label, SUM(Valor_Liquido) AS amount')
            ->groupBy('Vendedor');
    }

    /** Período (Data_Venda, datas inclusivas) e, se houver, as lojas escolhidas. */
    private function filtered(SalesFilter $f): Builder
    {
        return $this->view()
            ->whereBetween('Data_Venda', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($f->stores !== [], fn (Builder $q) => $q->whereIn('Loja', $f->stores));
    }

    private function view(): Builder
    {
        return DB::connection(self::CONNECTION)->table(self::VIEW);
    }
}

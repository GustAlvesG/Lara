<?php

namespace Tests\Unit;

use App\Services\MultiVendas\SalesDashboardRepository;
use App\Services\MultiVendas\SalesFilter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O SQL que o painel Vendas manda à view vw_DashboardVendas (SQL Server), sem
 * conectar: o servidor do MultiVendas não existe na suíte. Período e lojas
 * só como binding — o nome da loja vem do site.
 */
class SalesDashboardSqlTest extends TestCase
{
    private SalesDashboardRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SalesDashboardRepository();
    }

    private function filter(array $stores = []): SalesFilter
    {
        return new SalesFilter(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-12'), $stores);
    }

    public function test_periodo_e_lojas_vao_como_binding(): void
    {
        $evil = "Bar'; DROP TABLE x; --";
        $query = $this->repo->queryTotals($this->filter(['Gastrobar Piscina', $evil]));

        $sql = $query->toSql();
        $this->assertStringContainsString('from [vw_DashboardVendas]', $sql);
        $this->assertStringContainsString('[Data_Venda] between ? and ?', $sql);
        $this->assertStringContainsString('[Loja] in (?, ?)', $sql);
        $this->assertStringNotContainsString('DROP', $sql);
        $this->assertSame(['2026-11-01', '2026-11-12', 'Gastrobar Piscina', $evil], $query->getBindings());

        // Sem loja = todas: nenhum filtro de loja.
        $this->assertStringNotContainsString('[Loja] in', $this->repo->queryTotals($this->filter())->toSql());
    }

    public function test_cada_grafico_agrupa_como_o_grafana(): void
    {
        $f = $this->filter(['Loja A']);

        $this->assertStringContainsString('SUM(Valor_Liquido) AS total, COUNT(DISTINCT ID_Venda) AS sales', $this->repo->queryTotals($f)->toSql());
        $this->assertStringContainsString('group by [Loja]', $this->repo->queryByStore($f)->toSql());
        $this->assertStringContainsString('group by DATEPART(HOUR, Data_Hora_Venda)', $this->repo->queryByHour($f)->toSql());
        $this->assertStringContainsString('group by [Data_Venda]', $this->repo->queryByDay($f)->toSql());
        $this->assertStringContainsString('group by [Metodos_Pagamento]', $this->repo->queryPaymentMethods($f)->toSql());
        // Vendedores também respeitam a loja (no Grafana, não respeitavam).
        $sellers = $this->repo->querySellers($f)->toSql();
        $this->assertStringContainsString('group by [Vendedor]', $sellers);
        $this->assertStringContainsString('[Loja] in (?)', $sellers);

        $top = $this->repo->queryTopProducts($f)->toSql();
        $this->assertStringStartsWith('select top 10 ', $top);
        $this->assertStringContainsString('order by SUM(Valor_Liquido) DESC', $top);

        $this->assertSame('select distinct [Loja] from [vw_DashboardVendas] where [Loja] is not null order by [Loja] asc', $this->repo->queryStores()->toSql());
    }

    public function test_so_leitura(): void
    {
        $f = $this->filter(['Loja A']);
        foreach (['queryTotals', 'queryByStore', 'queryTopProducts', 'queryByHour', 'queryByDay', 'queryPaymentMethods', 'querySellers'] as $method) {
            $this->assertMatchesRegularExpression('/^select /', $this->repo->{$method}($f)->toSql(), $method);
        }
    }
}

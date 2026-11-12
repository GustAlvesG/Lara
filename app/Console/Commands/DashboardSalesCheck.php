<?php

namespace App\Console\Commands;

use App\Services\MultiVendas\SalesDashboardRepository;
use App\Services\MultiVendas\SalesFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Confere a fonte do painel Vendas dos dashboards do site: a conexão
 * `vendas_sqlsrv` abre, a view `vw_DashboardVendas` existe com as colunas
 * esperadas e as consultas do painel rodam. Só leitura; não mostra host,
 * usuário nem senha.
 */
class DashboardSalesCheck extends Command
{
    protected $signature = 'dashboards:vendas-check {--data= : Dia para somar (AAAA-MM-DD); padrão hoje}';

    protected $description = 'Confere a view vw_DashboardVendas do painel Vendas dos dashboards do site (só leitura)';

    /** Colunas da view que o painel usa. */
    public const COLUMNS = ['Data_Venda', 'Data_Hora_Venda', 'ID_Venda', 'Loja', 'Produto', 'Valor_Liquido', 'Metodos_Pagamento', 'Vendedor'];

    public function handle(SalesDashboardRepository $repository): int
    {
        try {
            $db = DB::connection(SalesDashboardRepository::CONNECTION);
            $this->line('Banco: ' . $db->selectOne('SELECT DB_NAME() AS n')->n);

            $columns = array_map(
                fn ($c) => $c->COLUMN_NAME,
                $db->select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [SalesDashboardRepository::VIEW]),
            );
            if ($columns === []) {
                $this->error('A view ' . SalesDashboardRepository::VIEW . ' não existe neste banco. Confira DB_VENDAS_DATABASE.');

                return self::FAILURE;
            }
            $missing = array_diff(self::COLUMNS, $columns);
            if ($missing !== []) {
                $this->error('Faltam colunas na view: ' . implode(', ', $missing));

                return self::FAILURE;
            }
            $this->info('View encontrada, com as colunas do painel.');

            $stores = $repository->stores();
            $this->line(count($stores) . ' loja(s): ' . implode(', ', $stores));

            $day = $this->option('data') ? now()->parse($this->option('data'))->startOfDay() : now()->startOfDay();
            $totals = $repository->totals(new SalesFilter($day, $day->copy()));
            $this->line(sprintf('%s: %d venda(s), R$ %s', $day->format('d/m/Y'), (int) $totals->sales, number_format((float) $totals->total, 2, ',', '.')));
        } catch (\Throwable $e) {
            $this->error('Falhou: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

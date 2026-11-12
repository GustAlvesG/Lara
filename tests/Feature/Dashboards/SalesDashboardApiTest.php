<?php

namespace Tests\Feature\Dashboards;

use App\Http\Controllers\Auth\LoginTokenController;
use App\Models\Member;
use App\Services\MultiVendas\SalesDashboardRepository;
use App\Services\MultiVendas\SalesFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Dashboards do site › Vendas (MultiVendas): só administrador, filtro de
 * período e lojas validado, a forma de cada gráfico, cache e o MultiVendas
 * fora do ar. A view é trocada por um repositório em memória (a suíte não
 * tem SQL Server; o SQL está em Tests\Unit\SalesDashboardSqlTest).
 */
class SalesDashboardApiTest extends TestCase
{
    use CreatesTicketSchema;

    private const ADMIN_CPF = '39053344705';
    private const USER_CPF = '86288366757';

    /** O repositório falso: guarda os filtros recebidos e pode "cair". */
    private object $repo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.api.token' => 'token-de-teste', 'services.jwt.secret' => 'segredo-de-teste', 'cache.default' => 'array']);
        // Datas relativas a hoje, sem Carbon::setTestNow: o JWT do sócio usa o
        // relógio real e nasceria vencido num dia "de mentira".

        $this->createTicketSchema();
        (require database_path('migrations/2026_11_12_100000_add_site_role_to_members.php'))->up();
        foreach ([[self::ADMIN_CPF, Member::ROLE_ADMIN], [self::USER_CPF, Member::ROLE_USER]] as [$cpf, $role]) {
            DB::table('members')->insert(['cpf' => $cpf, 'name' => 'Sócio ' . $cpf, 'title' => '1001', 'site_role' => $role]);
        }

        $this->repo = new class extends SalesDashboardRepository {
            public array $filters = [];
            public bool $down = false;

            public function stores(): array
            {
                $this->fail();

                return ['Gastrobar Piscina', 'Gastrobar Sede', 'Lanchonete'];
            }

            public function totals(SalesFilter $f): object
            {
                $this->filters[] = $f;
                $this->fail();

                return (object) ['total' => '1500.50', 'sales' => 30];
            }

            public function byStore(SalesFilter $f): array
            {
                return [(object) ['label' => 'Gastrobar Piscina', 'amount' => '500.00'], (object) ['label' => 'Gastrobar Sede', 'amount' => '700.00'], (object) ['label' => 'Lanchonete', 'amount' => '300.50']];
            }

            public function topProducts(SalesFilter $f, int $limit = 10): array
            {
                return [(object) ['label' => 'Chopp', 'amount' => '800'], (object) ['label' => null, 'amount' => '10']];
            }

            public function byHour(SalesFilter $f): array
            {
                return [(object) ['hour' => 11, 'amount' => '100'], (object) ['hour' => 14, 'amount' => '400']];
            }

            public function byDay(SalesFilter $f): array
            {
                // Como o SQL Server devolve date: às vezes com hora zerada.
                return [(object) ['day' => $f->from->toDateString() . ' 00:00:00.000', 'amount' => '1000', 'sales' => 20], (object) ['day' => $f->to->toDateString(), 'amount' => '500.50', 'sales' => 10]];
            }

            public function paymentMethods(SalesFilter $f): array
            {
                return [(object) ['label' => 'Pix', 'amount' => '900'], (object) ['label' => '', 'amount' => '600.50']];
            }

            public function sellers(SalesFilter $f): array
            {
                return [(object) ['label' => null, 'amount' => '200'], (object) ['label' => 'Maria', 'amount' => '1300.50']];
            }

            private function fail(): void
            {
                if ($this->down) {
                    throw new \PDOException('SQLSTATE[08001]: host 10.0.0.5 login lara falhou');
                }
            }
        };
        $this->app->instance(SalesDashboardRepository::class, $this->repo);
    }

    private function day(int $daysAgo = 0): string
    {
        return now()->subDays($daysAgo)->toDateString();
    }

    private function headers(string $cpf = self::ADMIN_CPF): array
    {
        return ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json', 'Session' => LoginTokenController::generate(['cpf' => $cpf])];
    }

    public function test_so_administrador_e_o_menu_mostra_vendas(): void
    {
        $this->getJson('/api/dashboard/sales', $this->headers(self::USER_CPF))->assertForbidden();
        $this->getJson('/api/dashboard/sales/stores', $this->headers(self::USER_CPF))->assertForbidden();

        $this->getJson('/api/dashboard/access', $this->headers())->assertOk()->assertJsonPath('panels.1.key', 'vendas')->assertJsonPath('panels.1.path', 'sales');
        $this->getJson('/api/dashboard/sales/stores', $this->headers())->assertOk()->assertJsonPath('stores.2', 'Lanchonete');
    }

    public function test_graficos_do_periodo(): void
    {
        $response = $this->getJson("/api/dashboard/sales?from={$this->day(2)}&to={$this->day()}&stores[]=Lanchonete&stores[]=Gastrobar%20Sede", $this->headers())->assertOk();

        $filter = $this->repo->filters[0];
        $this->assertSame([$this->day(2), $this->day(), ['Gastrobar Sede', 'Lanchonete']], [$filter->from->toDateString(), $filter->to->toDateString(), $filter->stores]);

        $response->assertJsonPath('period', ['from' => $this->day(2), 'to' => $this->day(), 'days' => 3])
            ->assertJsonPath('stores.all', false);

        $kpis = collect($response->json('kpis'))->pluck('value', 'key');
        $this->assertEquals([1500.5, 50.02, 30], [$kpis['net'], $kpis['average_ticket'], $kpis['sales']]);

        // Gastrobar vira uma barra só, como no Grafana; ordem do maior para o menor.
        $this->assertEquals([['label' => 'Gastrobar', 'amount' => 1200], ['label' => 'Lanchonete', 'amount' => 300.5]], $response->json('by_store'));
        $this->assertSame('Sem nome', $response->json('top_products.1.label'));
        // Horas sem buraco; dias do período inteiro, com zero.
        $this->assertSame([11, 12, 13, 14], array_column($response->json('by_hour'), 'hour'));
        $this->assertEquals([100, 0, 0, 400], array_column($response->json('by_hour'), 'amount'));
        $this->assertSame([$this->day(2), $this->day(1), $this->day()], array_column($response->json('by_day'), 'date'));
        $this->assertSame([20, 0, 10], array_column($response->json('by_day'), 'sales'));
        $this->assertSame(['Pix', 'Não informado'], array_column($response->json('payment_methods'), 'label'));
        $this->assertSame(['Maria', 'Não Identificado'], array_column($response->json('sellers'), 'label'));
    }

    public function test_padrao_e_hoje_e_todas_as_lojas_com_cache(): void
    {
        $this->getJson('/api/dashboard/sales', $this->headers())->assertOk()
            ->assertJsonPath('period', ['from' => $this->day(), 'to' => $this->day(), 'days' => 1])
            ->assertJsonPath('stores.all', true);
        $this->getJson('/api/dashboard/sales', $this->headers())->assertOk();

        // A segunda vez saiu do cache: a view foi consultada uma vez só.
        $this->assertCount(1, $this->repo->filters);
    }

    public function test_filtro_invalido(): void
    {
        $h = $this->headers();
        $this->getJson("/api/dashboard/sales?from={$this->day()}&to={$this->day(10)}", $h)->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson("/api/dashboard/sales?from={$this->day(400)}&to={$this->day()}", $h)->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/api/dashboard/sales?from=2026-02-30', $h)->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/api/dashboard/sales?from=12/11/2026', $h)->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/api/dashboard/sales?stores[]=Loja%20Fantasma', $h)->assertStatus(422)->assertJsonValidationErrors('stores');
        $this->assertSame([], $this->repo->filters);
    }

    public function test_multivendas_fora_do_ar_da_503_sem_vazar_o_driver(): void
    {
        $this->repo->down = true;

        $response = $this->getJson('/api/dashboard/sales', $this->headers())->assertStatus(503)
            ->assertJsonPath('error', 'O MultiVendas não respondeu. Tente de novo em instantes.');
        $this->assertStringNotContainsString('10.0.0.5', $response->getContent());
        $this->getJson('/api/dashboard/sales/stores', $this->headers())->assertStatus(503);
    }
}

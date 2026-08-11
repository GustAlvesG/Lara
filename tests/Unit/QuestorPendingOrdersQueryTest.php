<?php

namespace Tests\Unit;

use App\Exceptions\QuestorException;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * A consulta que define a fila de autorização.
 *
 * "Pendente de autorização" no Questor não é um status: autorizar não muda
 * `CD_STATUS`, só carimba `CD_USUARIO_AUTORIZOU`. Quem filtrar só por status
 * traz de volta tudo o que já foi decidido — por isso as três condições são
 * testadas juntas, e não é possível perder uma delas sem o teste avisar.
 *
 * Sem banco: a conexão do Questor é um duble que devolve a consulta recebida.
 */
class QuestorPendingOrdersQueryTest extends TestCase
{
    private array $executado = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'questor.enabled' => true,
            'questor.connection' => 'questor_sqlsrv',
            'questor.database' => 'FUNCSIDERURG',
            'questor.schema' => 'dbo',
            'questor.status.pendente' => 1,
            'questor.filiais' => [],
            'questor.desde' => null,
            'questor.limite_listagem' => 200,
        ]);

        $this->executado = [];

        $conexao = Mockery::mock();
        $conexao->shouldReceive('select')->andReturnUsing(function ($sql, $bindings = []) {
            $this->executado[] = ['sql' => $sql, 'bindings' => $bindings];

            return [];
        });

        DB::shouldReceive('connection')->andReturn($conexao);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function ultimaConsulta(): array
    {
        return $this->executado[count($this->executado) - 1];
    }

    public function test_a_fila_exige_pendente_sem_autorizador_e_sem_reprovador(): void
    {
        (new QuestorPurchaseOrders)->pending();

        $consulta = $this->ultimaConsulta();

        $this->assertStringContainsString('oc.CD_STATUS = ?', $consulta['sql']);
        $this->assertStringContainsString('oc.CD_USUARIO_AUTORIZOU IS NULL', $consulta['sql']);
        $this->assertStringContainsString('oc.CD_USUARIO_REPROVOU IS NULL', $consulta['sql']);
        $this->assertSame([1], $consulta['bindings']);
    }

    public function test_as_tabelas_sao_qualificadas_com_banco_e_schema(): void
    {
        (new QuestorPurchaseOrders)->pending();

        // Sem a qualificação, o login da Lara resolveria as tabelas no banco
        // default dele — que pode não ser o FUNCSIDERURG.
        $this->assertStringContainsString('FUNCSIDERURG.dbo.TBL_COMPRAS_ORDEM_COMPRA', $this->ultimaConsulta()['sql']);
    }

    public function test_busca_numerica_procura_pelo_numero_da_ordem(): void
    {
        (new QuestorPurchaseOrders)->pending(['busca' => '40975']);

        $consulta = $this->ultimaConsulta();

        $this->assertStringContainsString('oc.CD_ORDEM_COMPRA = ?', $consulta['sql']);
        $this->assertSame([1, 40975], $consulta['bindings']);
    }

    public function test_busca_textual_procura_no_fornecedor_e_no_solicitante(): void
    {
        (new QuestorPurchaseOrders)->pending(['busca' => 'Metalúrgica']);

        $consulta = $this->ultimaConsulta();

        $this->assertStringContainsString('ent.DS_ENTIDADE LIKE ?', $consulta['sql']);
        $this->assertStringContainsString('oc.DS_SOLICITANTE LIKE ?', $consulta['sql']);
        $this->assertSame([1, '%Metalúrgica%', '%Metalúrgica%', '%Metalúrgica%', '%Metalúrgica%'], $consulta['bindings']);
    }

    public function test_escopo_de_filiais_e_data_de_corte_entram_no_predicado(): void
    {
        config(['questor.filiais' => [1, 3], 'questor.desde' => '2026-01-01']);

        (new QuestorPurchaseOrders)->pending();

        $consulta = $this->ultimaConsulta();

        $this->assertStringContainsString('oc.CD_FILIAL IN (?, ?)', $consulta['sql']);
        $this->assertStringContainsString('oc.DT_CADASTRO >= ?', $consulta['sql']);
        $this->assertSame([1, 1, 3, '2026-01-01'], $consulta['bindings']);
    }

    public function test_contadores_usam_o_mesmo_predicado_da_listagem(): void
    {
        config(['questor.filiais' => [3]]);

        $orders = new QuestorPurchaseOrders;
        $orders->pending();
        $listagem = $this->ultimaConsulta();

        $orders->pendingSummary();
        $contadores = $this->ultimaConsulta();

        // Os dois precisam contar a mesma fila: um total maior que a lista, ou
        // menor, seria lido como "sumiu uma ordem".
        $this->assertSame($listagem['bindings'], $contadores['bindings']);
        $this->assertStringContainsString('COUNT(*)', $contadores['sql']);
    }

    public function test_a_listagem_respeita_o_teto_configurado(): void
    {
        config(['questor.limite_listagem' => 25]);

        (new QuestorPurchaseOrders)->pending();

        $this->assertStringContainsString('SELECT TOP (25)', $this->ultimaConsulta()['sql']);
    }

    public function test_modulo_desligado_nao_consulta_o_questor(): void
    {
        config(['questor.enabled' => false]);

        try {
            (new QuestorPurchaseOrders)->pending();
            $this->fail('Esperava QuestorException com o módulo desligado.');
        } catch (QuestorException $e) {
            $this->assertSame([], $this->executado);
        }
    }
}

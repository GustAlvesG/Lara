<?php

namespace Tests\Unit;

use App\Exceptions\QuestorException;
use App\Services\Questor\QuestorAuthorizationWriter;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * A simulação da autorização no Questor — o que esta versão do módulo faz no
 * lugar de gravar.
 *
 * O que está sob teste é justamente o que não dá para conferir olhando a tela:
 * que o `UPDATE` montado é o certo (aprovar não mexe no status, reprovar mexe),
 * que os parâmetros saem na ordem dos `?`, e — o mais importante — que
 * **nenhum UPDATE chega ao banco**. A única instrução que a simulação executa é
 * o `SELECT COUNT(*)` que conta as linhas que o comando pegaria.
 *
 * Sem banco: a conexão do Questor é substituída por um duble que grava tudo o
 * que recebe.
 */
class QuestorAuthorizationSimulationTest extends TestCase
{
    /** Tudo o que foi executado na conexão do Questor durante o teste. */
    private array $executado = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'questor.enabled' => true,
            'questor.dry_run' => true,
            'questor.connection' => 'questor_sqlsrv',
            'questor.database' => 'FUNCSIDERURG',
            'questor.schema' => 'dbo',
            'questor.usuario_tecnico' => 42,
            'questor.motivo_max' => 100,
        ]);

        $this->executado = [];
        $this->gravado = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** Os UPDATEs enviados à conexão do Questor. Vazio é o esperado ao simular. */
    private array $gravado = [];

    /**
     * A conexão do Questor, trocada por um duble.
     *
     * `$linhas` é o que o `SELECT COUNT(*)` devolve; `$afetadas`, o retorno do
     * `UPDATE`. Os dois caminhos são gravados separadamente para os testes
     * poderem afirmar não só o que foi consultado, mas que **nada foi escrito**.
     */
    private function fakeConnection(int $linhas = 1, int $afetadas = 1): void
    {
        $conexao = Mockery::mock();

        $conexao->shouldReceive('select')->andReturnUsing(function ($sql, $bindings = []) use ($linhas) {
            $this->executado[] = ['sql' => $sql, 'bindings' => $bindings];

            return [(object) ['TOTAL' => $linhas]];
        });

        $conexao->shouldReceive('update')->andReturnUsing(function ($sql, $bindings = []) use ($afetadas) {
            $this->executado[] = ['sql' => $sql, 'bindings' => $bindings];
            $this->gravado[] = ['sql' => $sql, 'bindings' => $bindings];

            return $afetadas;
        });

        DB::shouldReceive('connection')->andReturn($conexao);
    }

    /**
     * Leitura de ordens sem banco: devolve a ordem e o usuário técnico prontos.
     */
    private function orders(array $ordem = [], ?object $tecnico = null): QuestorPurchaseOrders
    {
        $ordemPadrao = (object) array_merge([
            'CD_ORDEM_COMPRA' => 40975,
            'CD_STATUS' => 1,
            'DS_STATUS' => 'PENDENTE',
            'CD_USUARIO_AUTORIZOU' => null,
            'DT_AUTORIZACAO' => null,
            'CD_USUARIO_REPROVOU' => null,
            'DT_REPROVACAO' => null,
            'DS_MOTIVO_REPROVADO' => null,
        ], $ordem);

        $usuario = $tecnico ?? (object) [
            'CD_CODUSUARIO' => 42,
            'DS_USUARIO' => 'Integração Lara',
            'DS_LOGIN' => 'LARA',
            'X_ATIVO' => 1,
            'X_AUTORIZA_ORDEM_COMPRA' => 1,
            'X_REPROVA_ORDEM_COMPRA' => 1,
        ];

        return new class($ordemPadrao, $usuario) extends QuestorPurchaseOrders
        {
            public function __construct(private object $ordem, private ?object $tecnico)
            {
            }

            public function find(int $cdOrdemCompra): object
            {
                return $this->ordem;
            }

            public function technicalUser(): ?object
            {
                return $this->tecnico;
            }
        };
    }

    private function writer(array $ordem = [], ?object $tecnico = null): QuestorAuthorizationWriter
    {
        return new QuestorAuthorizationWriter($this->orders($ordem, $tecnico));
    }

    public function test_aprovacao_grava_o_carimbo_e_nao_mexe_no_status(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->approve(40975);

        $this->assertStringContainsString('SET CD_USUARIO_AUTORIZOU = ?', $previa['sql']);
        $this->assertStringContainsString('DT_AUTORIZACAO = GETDATE()', $previa['sql']);
        // Autorizar mantém a ordem PENDENTE — o status só aparece no WHERE,
        // como proteção, nunca no SET.
        $this->assertStringNotContainsString('SET CD_STATUS', $previa['sql']);
        $this->assertStringContainsString('AND CD_STATUS = ?', $previa['sql']);
        $this->assertStringContainsString('AND CD_USUARIO_AUTORIZOU IS NULL', $previa['sql']);

        // Usuário técnico, ordem e status pendente — nessa ordem.
        $this->assertSame([42, 40975, 1], $previa['bindings']);
    }

    public function test_carimbo_em_ds_obs_acrescenta_sem_substituir(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->approve(40975, observacao: "\n[LARA #7] Autorizado pelo Lara.");

        // COALESCE + concatenação: acrescenta ao que já está lá. Um
        // `DS_OBS = ?` apagaria a anotação do comprador.
        $this->assertStringContainsString("DS_OBS = LEFT(COALESCE(DS_OBS, '') + ?, 5000)", $previa['sql']);

        // Os bindings do SET vêm antes dos do WHERE.
        $this->assertSame(
            [42, "\n[LARA #7] Autorizado pelo Lara.", 40975, 1],
            $previa['bindings']
        );
    }

    public function test_sem_observacao_o_update_nao_toca_em_ds_obs(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->approve(40975);

        $this->assertStringNotContainsString('DS_OBS', $previa['sql']);
        $this->assertSame([42, 40975, 1], $previa['bindings']);
    }

    public function test_simulacao_nao_executa_nenhum_update(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->approve(40975);

        $this->assertFalse($previa['executado']);
        $this->assertTrue($previa['dry_run']);

        // Uma única instrução foi ao banco, e é a contagem.
        $this->assertSame([], $this->gravado);
        $this->assertCount(1, $this->executado);
        $this->assertStringStartsWith('SELECT COUNT(*)', $this->executado[0]['sql']);
    }

    public function test_contagem_usa_o_mesmo_predicado_do_update(): void
    {
        $this->fakeConnection(linhas: 1);

        $previa = $this->writer()->approve(40975);

        $contagem = $this->executado[0];

        $this->assertStringContainsString('CD_ORDEM_COMPRA = ?', $contagem['sql']);
        $this->assertStringContainsString('CD_STATUS = ?', $contagem['sql']);
        $this->assertStringContainsString('CD_USUARIO_AUTORIZOU IS NULL', $contagem['sql']);
        $this->assertSame([40975, 1], $contagem['bindings']);
        $this->assertSame(1, $previa['linhas_afetadas']);
        $this->assertSame([], $previa['impedimentos']);
    }

    public function test_reprovacao_move_para_reprovado_e_guarda_o_status_anterior(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->reject(40975, 'Fora do orçamento');

        $this->assertStringContainsString('SET CD_STATUS = ?', $previa['sql']);
        $this->assertStringContainsString('CD_STATUS_ANTERIOR = ?', $previa['sql']);
        $this->assertStringContainsString('DS_MOTIVO_REPROVADO = ?', $previa['sql']);
        $this->assertStringContainsString('AND CD_USUARIO_REPROVOU IS NULL', $previa['sql']);

        // REPROVADO, status anterior PENDENTE, usuário técnico, motivo, ordem,
        // status pendente (do WHERE).
        $this->assertSame([5, 1, 42, 'Fora do orçamento', 40975, 1], $previa['bindings']);
    }

    public function test_motivo_e_truncado_no_tamanho_da_coluna(): void
    {
        $this->fakeConnection();

        $motivo = str_repeat('a', 150);

        $previa = $this->writer()->reject(40975, $motivo);

        // DS_MOTIVO_REPROVADO é varchar(100): sem o corte, o SQL Server recusa
        // a linha inteira.
        $this->assertSame(100, mb_strlen($previa['bindings'][3]));
    }

    public function test_reprovacao_sem_motivo_e_recusada(): void
    {
        $this->fakeConnection();

        $this->expectException(QuestorException::class);

        $this->writer()->reject(40975, '   ');
    }

    public function test_com_o_dry_run_desligado_a_aprovacao_grava_de_verdade(): void
    {
        config(['questor.dry_run' => false]);
        $this->fakeConnection(linhas: 1, afetadas: 1);

        $resultado = $this->writer()->approve(40975);

        $this->assertTrue($resultado['executado']);
        $this->assertFalse($resultado['dry_run']);
        $this->assertSame(1, $resultado['linhas_afetadas']);

        // O UPDATE foi ao banco, e é o de autorização.
        $this->assertCount(1, $this->gravado);
        $this->assertStringContainsString('SET CD_USUARIO_AUTORIZOU = ?', $this->gravado[0]['sql']);
        $this->assertSame([42, 40975, 1], $this->gravado[0]['bindings']);
    }

    public function test_gravacao_confirma_relendo_a_ordem_no_questor(): void
    {
        config(['questor.dry_run' => false]);
        $this->fakeConnection();

        $resultado = $this->writer()->approve(40975);

        // A confirmação é a ordem relida — a prova do carimbo, não a suposição
        // de que ele entrou porque o UPDATE não deu erro.
        $this->assertNotNull($resultado['confirmacao']);
        $this->assertArrayHasKey('CD_USUARIO_AUTORIZOU', $resultado['confirmacao']);
    }

    public function test_gravacao_que_nao_pega_nenhuma_linha_nao_e_sucesso(): void
    {
        config(['questor.dry_run' => false]);
        // A contagem ainda vê a ordem na fila, mas entre a contagem e o UPDATE
        // alguém decidiu pela tela nativa: o UPDATE pega zero.
        $this->fakeConnection(linhas: 1, afetadas: 0);

        $resultado = $this->writer()->approve(40975);

        $this->assertTrue($resultado['executado']);
        $this->assertSame(0, $resultado['linhas_afetadas']);
    }

    public function test_impedimento_recusa_a_gravacao_antes_de_chegar_ao_questor(): void
    {
        config(['questor.dry_run' => false]);
        $this->fakeConnection();

        try {
            $this->writer(tecnico: (object) [
                'CD_CODUSUARIO' => 42,
                'DS_USUARIO' => 'Integração Lara',
                'DS_LOGIN' => 'LARA',
                'X_ATIVO' => 0,   // inativo
                'X_AUTORIZA_ORDEM_COMPRA' => 1,
                'X_REPROVA_ORDEM_COMPRA' => 1,
            ])->approve(40975);

            $this->fail('Esperava a recusa por impedimento.');
        } catch (QuestorException $e) {
            // Na simulação impedimentos são informativos; na gravação são a
            // última barreira, e nada pode ter ido ao ERP.
            $this->assertSame([], $this->gravado);
            $this->assertStringContainsString('inativo', $e->getMessage());
        }
    }

    public function test_reprovacao_nao_grava_sem_a_trava_propria(): void
    {
        config(['questor.dry_run' => false, 'questor.reprovacao_liberada' => false]);
        $this->fakeConnection();

        try {
            $this->writer()->reject(40975, 'Fora do orçamento');
            $this->fail('Esperava a recusa da reprovação.');
        } catch (QuestorException $e) {
            $this->assertSame([], $this->gravado);
            $this->assertStringContainsString('6.2', $e->getMessage());
        }
    }

    public function test_reprovacao_grava_quando_a_trava_propria_esta_ligada(): void
    {
        config(['questor.dry_run' => false, 'questor.reprovacao_liberada' => true]);
        $this->fakeConnection();

        $resultado = $this->writer()->reject(40975, 'Fora do orçamento');

        $this->assertTrue($resultado['executado']);
        $this->assertCount(1, $this->gravado);
        $this->assertStringContainsString('SET CD_STATUS = ?', $this->gravado[0]['sql']);
    }

    public function test_simular_forcado_nao_grava_mesmo_com_a_gravacao_ligada(): void
    {
        config(['questor.dry_run' => false]);
        $this->fakeConnection();

        // É o que o comando `questor:testar` usa: ele promete não gravar, e a
        // promessa não pode depender de como o .env está no dia.
        $resultado = $this->writer()->approve(40975, simular: true);

        $this->assertFalse($resultado['executado']);
        $this->assertTrue($resultado['dry_run']);
        $this->assertSame([], $this->gravado);
    }

    public function test_modulo_desligado_recusa_a_simulacao(): void
    {
        config(['questor.enabled' => false]);

        $this->expectException(QuestorException::class);

        $this->writer()->approve(40975);
    }

    public function test_usuario_tecnico_sem_permissao_vira_impedimento(): void
    {
        $this->fakeConnection();

        $previa = $this->writer(tecnico: (object) [
            'CD_CODUSUARIO' => 42,
            'DS_USUARIO' => 'Integração Lara',
            'DS_LOGIN' => 'LARA',
            'X_ATIVO' => 1,
            'X_AUTORIZA_ORDEM_COMPRA' => 0,
            'X_REPROVA_ORDEM_COMPRA' => 1,
        ])->approve(40975);

        $this->assertNotEmpty($previa['impedimentos']);
        $this->assertStringContainsString('X_AUTORIZA_ORDEM_COMPRA', implode(' ', $previa['impedimentos']));
    }

    public function test_usuario_tecnico_nao_configurado_vira_impedimento(): void
    {
        config(['questor.usuario_tecnico' => null]);
        $this->fakeConnection();

        $previa = $this->writer(tecnico: null)->approve(40975);

        $this->assertStringContainsString('QUESTOR_USUARIO_TECNICO', implode(' ', $previa['impedimentos']));
        // Sem usuário técnico o binding vai nulo — e é por isso que a gravação
        // fica impedida em vez de gravar um autorizador vazio.
        $this->assertNull($previa['bindings'][0]);
    }

    public function test_ordem_fora_da_fila_avisa_que_nenhuma_linha_seria_afetada(): void
    {
        $this->fakeConnection(linhas: 0);

        $previa = $this->writer(['CD_USUARIO_AUTORIZOU' => 30])->approve(40975);

        $this->assertSame(0, $previa['linhas_afetadas']);
        $this->assertStringContainsString('Nenhuma linha seria afetada', implode(' ', $previa['impedimentos']));
        $this->assertStringContainsString('já tem autorizador', implode(' ', $previa['impedimentos']));
    }

    public function test_reprovacao_traz_a_ressalva_do_teste_ao_vivo_pendente(): void
    {
        $this->fakeConnection();

        $previa = $this->writer()->reject(40975, 'Fora do orçamento');

        // O caminho de reprovação só tem evidência histórica; enquanto o teste
        // da seção 6.2 não for feito, a tela precisa dizer isso.
        $this->assertNotEmpty($previa['ressalvas']);
    }
}

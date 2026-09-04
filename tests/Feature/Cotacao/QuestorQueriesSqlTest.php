<?php

namespace Tests\Feature\Cotacao;

use App\Services\Questor\QuestorCadastroRepository;
use App\Services\Questor\QuestorCompraRepository;
use App\Services\Questor\QuestorSolicitacaoRepository;
use Tests\TestCase;

/**
 * A forma do SQL que sai dos repositórios do Questor.
 *
 * POR QUE ISTO EXISTE: estas consultas só rodariam de verdade contra o ERP, que
 * a suíte não tem. O resultado é que um erro de montagem — um argumento de
 * `sprintf` fora de ordem, um `%s` esquecido, um binding a mais — só apareceria
 * em produção, na forma de "o Questor não respondeu".
 *
 * Já aconteceu uma vez durante a implementação: em `fornecedoresDoMaterial` o
 * filtro de status estava depois das tabelas na lista de argumentos, mas antes
 * delas no texto da consulta. O nome da tabela foi parar dentro do `WHERE` e o
 * filtro no lugar de um `LEFT JOIN`. A contagem de `?` continuava batendo — é
 * a checagem de "todo FROM/JOIN aponta para uma tabela qualificada" que pega.
 *
 * Os repositórios são subclassados para interceptar `select()`: nada é enviado
 * a conexão nenhuma.
 */
class QuestorQueriesSqlTest extends TestCase
{
    /** Aliases de CTE que podem aparecer depois de FROM/JOIN sem serem tabela. */
    private const CTES = ['COMPRAS', 'ITENS'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('questor.enabled', true);
        config()->set('questor.database', 'FUNCSIDERURG');
        config()->set('questor.schema', 'dbo');
        // Cache desligado: senão a consulta nem chega a ser montada na segunda
        // chamada, e o teste passaria sem olhar nada.
        config()->set('questor.cotacao.cache_ttl', 0);
        config()->set('questor.cotacao.status_nf_entrada_cancelada', 4);
    }

    public function test_consultas_de_compra_estao_bem_formadas(): void
    {
        $repo = $this->compras();

        $repo->ultimaCompraPorItem(34334);
        $repo->historicoDoMaterial(5001);
        $repo->fornecedoresDoMaterial(5001);
        $repo->fornecedoresDaSolicitacao(34334);
        $repo->fornecedoresHomologados(5001);
        $repo->cotacoesAnteriores(5001);
        $repo->ordensDoMaterial(5001);

        $this->assertCount(7, $repo->capturado);

        foreach ($repo->capturado as $i => $consulta) {
            $this->assertConsultaBemFormada($consulta, "consulta de compra #{$i}");
        }
    }

    public function test_consultas_de_solicitacao_estao_bem_formadas(): void
    {
        $repo = $this->solicitacoes();

        $repo->buscar(34334);
        $repo->itens(34334);
        $repo->procurar('2026-01-01', '2026-12-31', 'tinta');
        $repo->procurar(null, null, null);

        $this->assertCount(4, $repo->capturado);

        foreach ($repo->capturado as $i => $consulta) {
            $this->assertConsultaBemFormada($consulta, "consulta de solicitação #{$i}");
        }
    }

    public function test_consultas_de_cadastro_estao_bem_formadas(): void
    {
        $repo = $this->cadastros();

        $repo->fretes();
        $repo->prazosEntrega();
        $repo->formasPagamento();
        $repo->buscarFornecedores('TINTAS');
        $repo->buscarMateriais('TINTA ESMALTE');
        $repo->statusDasNotasEntrada();
        $repo->operacoesEmUso();

        $this->assertCount(7, $repo->capturado);

        foreach ($repo->capturado as $i => $consulta) {
            $this->assertConsultaBemFormada($consulta, "consulta de cadastro #{$i}");
        }
    }

    /**
     * Armadilha nº 2: `TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS` não tem FK.
     * Um `INNER JOIN` com `TBL_STATUS` sumiria com notas em silêncio.
     */
    public function test_todo_join_com_a_tabela_de_status_e_left_join(): void
    {
        $repos = [$this->compras(), $this->solicitacoes()];

        $repos[0]->ultimaCompraPorItem(34334);
        $repos[0]->historicoDoMaterial(5001);
        $repos[0]->ordensDoMaterial(5001);
        $repos[1]->buscar(34334);
        $repos[1]->procurar('2026-01-01', '2026-12-31', null);

        foreach ($repos as $repo) {
            foreach ($repo->capturado as $consulta) {
                $sql = $consulta['sql'];

                if (! str_contains($sql, 'TBL_STATUS')) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/(?<!LEFT )\bJOIN\s+FUNCSIDERURG\.dbo\.TBL_STATUS\b/',
                    $sql,
                    'TBL_STATUS só pode entrar por LEFT JOIN: a coluna CD_STATUS não tem FK declarada.'
                );
            }
        }
    }

    /**
     * Armadilha nº 3: o que conta como compra é o flag do próprio Questor.
     */
    public function test_consultas_de_historico_filtram_pelo_flag_de_compra(): void
    {
        $repo = $this->compras();

        $repo->ultimaCompraPorItem(34334);
        $repo->historicoDoMaterial(5001);
        $repo->fornecedoresDoMaterial(5001);
        $repo->fornecedoresDaSolicitacao(34334);

        foreach ($repo->capturado as $i => $consulta) {
            $this->assertStringContainsString(
                'ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1',
                $consulta['sql'],
                "A consulta de histórico #{$i} precisa excluir devolução, transferência e remessa."
            );
        }
    }

    /**
     * Sem o código configurado, nenhuma nota é descartada por status — e o
     * binding correspondente também não aparece.
     */
    public function test_sem_status_configurado_o_filtro_e_o_binding_somem_juntos(): void
    {
        config()->set('questor.cotacao.status_nf_entrada_cancelada', null);

        $repo = $this->compras();
        $repo->ultimaCompraPorItem(34334);

        $consulta = $repo->capturado[0];

        $this->assertStringNotContainsString('CD_STATUS <>', $consulta['sql']);
        $this->assertSame([34334], $consulta['bindings']);
        $this->assertConsultaBemFormada($consulta, 'sem filtro de status');
    }

    public function test_com_status_configurado_o_filtro_entra_com_o_binding_na_posicao_certa(): void
    {
        $repo = $this->compras();
        $repo->ultimaCompraPorItem(34334);

        $consulta = $repo->capturado[0];

        $this->assertStringContainsString('AND e.CD_STATUS <> ?', $consulta['sql']);
        // O filtro está DENTRO do OUTER APPLY, que vem antes do WHERE de fora:
        // o binding do status precede o da solicitação.
        $this->assertSame([4, 34334], $consulta['bindings']);
    }

    /**
     * Nenhum valor de tela é concatenado: o termo de busca vai por binding, com
     * os curingas montados em PHP.
     */
    public function test_termo_de_busca_vai_por_binding_e_nao_concatenado(): void
    {
        $repo = $this->solicitacoes();
        $repo->procurar('2026-01-01', '2026-12-31', "tinta' OR 1=1 --");

        $consulta = $repo->capturado[0];

        $this->assertStringNotContainsString('OR 1=1', $consulta['sql']);
        $this->assertContains("%tinta' OR 1=1 --%", $consulta['bindings']);
    }

    /**
     * Termo curto não vira consulta: o autocomplete dispara a cada tecla, e
     * `LIKE '%a%'` em TBL_ENTIDADES varreria o cadastro inteiro.
     */
    public function test_autocomplete_nao_consulta_com_termo_curto(): void
    {
        $repo = $this->cadastros();

        $this->assertCount(0, $repo->buscarFornecedores('a'));
        $this->assertCount(0, $repo->buscarMateriais('t'));
        $this->assertSame([], $repo->capturado);
    }

    // ------------------------------------------------------------------

    /**
     * @param  array{sql: string, bindings: array<int, mixed>}  $consulta
     */
    private function assertConsultaBemFormada(array $consulta, string $contexto): void
    {
        $sql = $consulta['sql'];

        // 1. Nenhum marcador de sprintf sobrou — sinal de argumento faltando.
        $this->assertDoesNotMatchRegularExpression(
            '/%[sd]/',
            $sql,
            "{$contexto}: sobrou marcador de sprintf no SQL."
        );

        // 2. Um binding para cada `?`. Pega binding a mais ou a menos.
        $this->assertSame(
            substr_count($sql, '?'),
            count($consulta['bindings']),
            "{$contexto}: a quantidade de `?` não bate com a de bindings."
        );

        // 3. Todo FROM/JOIN aponta para uma tabela qualificada (ou uma CTE).
        //    É esta a checagem que pega argumento de sprintf fora de ordem.
        preg_match_all('/\b(?:FROM|JOIN)\s+(\S+)/i', $sql, $encontrados);

        foreach ($encontrados[1] as $alvo) {
            if (in_array(strtoupper($alvo), self::CTES, true)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^FUNCSIDERURG\.dbo\.TBL_[A-Z_]+$/',
                $alvo,
                "{$contexto}: `{$alvo}` deveria ser uma tabela qualificada do Questor."
            );
        }

        // 4. A consulta começa por SELECT ou WITH — a trava de leitura.
        $this->assertMatchesRegularExpression(
            '/^\s*(SELECT|WITH)\b/i',
            $sql,
            "{$contexto}: o repositório só pode produzir leitura."
        );
    }

    /**
     * Repositórios com `select()` interceptado — nada chega à conexão.
     */
    private function compras(): QuestorCompraRepository
    {
        return new class extends QuestorCompraRepository
        {
            /** @var array<int, array{sql: string, bindings: array<int, mixed>}> */
            public array $capturado = [];

            protected function select(string $sql, array $bindings = []): array
            {
                $this->capturado[] = ['sql' => $sql, 'bindings' => $bindings];

                return [];
            }
        };
    }

    private function solicitacoes(): QuestorSolicitacaoRepository
    {
        return new class extends QuestorSolicitacaoRepository
        {
            /** @var array<int, array{sql: string, bindings: array<int, mixed>}> */
            public array $capturado = [];

            protected function select(string $sql, array $bindings = []): array
            {
                $this->capturado[] = ['sql' => $sql, 'bindings' => $bindings];

                return [];
            }
        };
    }

    private function cadastros(): QuestorCadastroRepository
    {
        return new class extends QuestorCadastroRepository
        {
            /** @var array<int, array{sql: string, bindings: array<int, mixed>}> */
            public array $capturado = [];

            protected function select(string $sql, array $bindings = []): array
            {
                $this->capturado[] = ['sql' => $sql, 'bindings' => $bindings];

                return [];
            }
        };
    }
}

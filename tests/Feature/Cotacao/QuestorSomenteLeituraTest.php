<?php

namespace Tests\Feature\Cotacao;

use App\Exceptions\QuestorException;
use App\Services\Questor\QuestorReadRepository;
use Tests\TestCase;

/**
 * A regra inegociável do módulo: o banco do Questor é SOMENTE LEITURA.
 *
 * Nada aqui substitui a permissão de banco — **o login usado pelo módulo tem de
 * ter apenas SELECT, concedido pelo DBA**. Estes testes cobrem a segunda linha
 * de defesa: o caminho de acesso que os repositórios de cotação usam recusa
 * qualquer instrução que não seja leitura, antes de chegar à conexão.
 *
 * A recusa não depende de o módulo estar ligado: uma tentativa de escrita é
 * recusada em qualquer configuração de ambiente.
 */
class QuestorSomenteLeituraTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function instrucoesDeEscrita(): array
    {
        return [
            'update' => ['UPDATE dbo.TBL_COMPRAS_SOLICITACAO SET DS_OBS = ? WHERE CD_SOLICITACAO = ?'],
            'insert' => ['INSERT INTO dbo.TBL_COMPRAS_COTACAO (CD_COTACAO) VALUES (?)'],
            'delete' => ['DELETE FROM dbo.TBL_COMPRAS_SOLICITACAO_ITENS WHERE CD_SOLICITACAO = ?'],
            'exec' => ['EXEC sp_who'],
            'truncate' => ['TRUNCATE TABLE dbo.TBL_COMPRAS_COTACAO'],
            'drop' => ['DROP TABLE dbo.TBL_COMPRAS_COTACAO'],
            'merge' => ['MERGE dbo.TBL_MATERIAIS AS alvo USING dbo.TBL_MATERIAIS AS origem ON 1 = 1'],
            'update depois de ponto-e-vírgula' => ['; UPDATE dbo.TBL_MATERIAIS SET X_ATIVO = 0'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('instrucoesDeEscrita')]
    public function test_instrucao_de_escrita_e_recusada_antes_de_chegar_a_conexao(string $sql): void
    {
        config()->set('questor.enabled', true);

        $this->expectException(QuestorException::class);
        $this->expectExceptionMessage('só lê o Questor');

        $this->repositorio()->rodar($sql);
    }

    /**
     * Mesmo com a integração desligada — quando `ensureEnabled()` também
     * recusaria — a mensagem tem de ser a da trava de escrita, não a de
     * "módulo desligado". Errar isso esconderia a tentativa de escrita.
     */
    public function test_a_recusa_de_escrita_nao_depende_de_o_modulo_estar_ligado(): void
    {
        config()->set('questor.enabled', false);

        $this->expectException(QuestorException::class);
        $this->expectExceptionMessage('Nenhuma escrita no ERP pode sair daqui');

        $this->repositorio()->rodar('UPDATE dbo.TBL_MATERIAIS SET X_ATIVO = 0');
    }

    /**
     * SELECT e WITH (as CTEs das queries 5.b e 5.c) passam pela trava — o que
     * barra a partir daí é o módulo estar desligado, com a mensagem certa.
     */
    public function test_select_e_with_passam_pela_trava(): void
    {
        config()->set('questor.enabled', false);

        foreach (['SELECT 1', "  \n WITH X AS (SELECT 1 AS A) SELECT * FROM X"] as $sql) {
            try {
                $this->repositorio()->rodar($sql);
                $this->fail('Esperava a recusa por módulo desligado.');
            } catch (QuestorException $e) {
                $this->assertStringContainsString('está desligada', $e->getMessage());
            }
        }
    }

    public function test_o_nome_da_tabela_e_qualificado_com_banco_e_schema(): void
    {
        config()->set('questor.database', 'FUNCSIDERURG');
        config()->set('questor.schema', 'dbo');

        $this->assertSame(
            'FUNCSIDERURG.dbo.TBL_COMPRAS_SOLICITACAO',
            $this->repositorio()->nomeDaTabela('TBL_COMPRAS_SOLICITACAO')
        );
    }

    /**
     * O código do status "cancelada" NÃO é chutado: sem configuração, o filtro
     * simplesmente não entra. Incluir uma nota cancelada de vez em quando é
     * menos grave que esconder compras boas do histórico por um chute errado.
     */
    public function test_sem_configuracao_nenhuma_nota_e_descartada_por_status(): void
    {
        config()->set('questor.cotacao.status_nf_entrada_cancelada', null);

        [$sql, $bindings] = $this->repositorio()->filtroDeCancelada('e');

        $this->assertSame('', $sql);
        $this->assertSame([], $bindings);
    }

    public function test_com_configuracao_o_filtro_entra_por_binding_e_nao_concatenado(): void
    {
        config()->set('questor.cotacao.status_nf_entrada_cancelada', 4);

        [$sql, $bindings] = $this->repositorio()->filtroDeCancelada('ent');

        $this->assertSame(' AND ent.CD_STATUS <> ?', $sql);
        $this->assertSame([4], $bindings);
    }

    /**
     * Repositório de teste: expõe os métodos protegidos da base sem abrir o
     * acesso à conexão para o resto da aplicação.
     */
    private function repositorio(): object
    {
        return new class extends QuestorReadRepository
        {
            /** @param array<int, mixed> $bindings */
            public function rodar(string $sql, array $bindings = []): array
            {
                return $this->select($sql, $bindings);
            }

            public function nomeDaTabela(string $nome): string
            {
                return $this->table($nome);
            }

            /** @return array{0: string, 1: array<int, mixed>} */
            public function filtroDeCancelada(string $alias): array
            {
                return $this->filtroNaoCancelada($alias);
            }
        };
    }
}

<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centros de custo do Questor — só leitura.
 *
 * Duas perguntas diferentes, e a distinção importa para a tela de relação:
 *
 *  - {@see self::active()} devolve o **cadastro** inteiro. É o que o gerente
 *    pode vir a precisar, inclusive centros de custo que ainda não apareceram
 *    em nenhuma ordem.
 *  - {@see self::inUse()} devolve só os que aparecem nos itens de ordens
 *    pendentes. São 35 hoje, contra o cadastro completo — é por eles que a tela
 *    começa, porque são os que decidem a fila de verdade.
 *
 * O centro de custo é coluna do **item**, não do cabeçalho: uma ordem tem
 * tantos quantos seus itens tiverem, e há itens sem nenhum. Ver
 * {@see self::forOrder()}.
 */
class QuestorCostCenters
{
    /**
     * O cadastro de centros de custo ativos.
     *
     * @return Collection<int, object>
     *
     * @throws QuestorException
     */
    public function active(): Collection
    {
        $sql = sprintf(
            'SELECT cc.CD_CENTRO_CUSTO, cc.DS_CENTRO_CUSTO, cc.DS_CLASSIFICACAO, cc.CD_EMPRESA
             FROM %s cc
             WHERE cc.X_ATIVO = 1
             ORDER BY cc.CD_CENTRO_CUSTO',
            $this->table('SEL_CUSTOS_CENTRO_CUSTOS')
        );

        return collect($this->select($sql, []));
    }

    /**
     * Os centros de custo que aparecem nas ordens pendentes, com quantas ordens
     * e quanto valor dependem de cada um — a tela ordena por isso, para o
     * cadastro começar pelo que trava mais dinheiro.
     *
     * @return Collection<int, object>
     *
     * @throws QuestorException
     */
    public function inUse(): Collection
    {
        $sql = sprintf(
            'SELECT it.CD_CENTRO_CUSTO,
                    MAX(cc.DS_CENTRO_CUSTO) AS DS_CENTRO_CUSTO,
                    COUNT(DISTINCT it.CD_ORDEM_COMPRA) AS ORDENS,
                    SUM(it.VL_TOTAL) AS VALOR
             FROM %s it
             JOIN %s oc ON oc.CD_ORDEM_COMPRA = it.CD_ORDEM_COMPRA
             LEFT JOIN %s cc ON cc.CD_CENTRO_CUSTO = it.CD_CENTRO_CUSTO
             WHERE oc.CD_STATUS = ?
               AND oc.CD_USUARIO_AUTORIZOU IS NULL
               AND oc.CD_USUARIO_REPROVOU IS NULL
               AND it.CD_CENTRO_CUSTO IS NOT NULL
             GROUP BY it.CD_CENTRO_CUSTO
             ORDER BY SUM(it.VL_TOTAL) DESC',
            $this->table('TBL_COMPRAS_ORDEM_COMPRA_ITENS'),
            $this->table('TBL_COMPRAS_ORDEM_COMPRA'),
            $this->table('SEL_CUSTOS_CENTRO_CUSTOS'),
        );

        return collect($this->select($sql, [(int) config('questor.status.pendente', 1)]));
    }

    /**
     * Os centros de custo de uma ordem, lidos dos itens dela.
     *
     * Devolve também `sem_centro_custo`, e não é detalhe: quase um terço das
     * ordens pendentes tem pelo menos um item sem centro de custo preenchido.
     * Para essas, não há diretor sugerido — quem escolhe é o gerente, no nível
     * 2, e a tela dele precisa saber disso para pedir a escolha em vez de
     * mostrar uma lista vazia sem explicação.
     *
     * @return array{codigos: array<int, int>, sem_centro_custo: bool}
     *
     * @throws QuestorException
     */
    public function forOrder(int $cdOrdemCompra): array
    {
        $sql = sprintf(
            'SELECT DISTINCT it.CD_CENTRO_CUSTO
             FROM %s it
             WHERE it.CD_ORDEM_COMPRA = ?',
            $this->table('TBL_COMPRAS_ORDEM_COMPRA_ITENS')
        );

        $linhas = collect($this->select($sql, [$cdOrdemCompra]));

        return [
            'codigos' => $linhas
                ->pluck('CD_CENTRO_CUSTO')
                ->filter(fn($c) => $c !== null)
                ->map(fn($c) => (int) $c)
                ->unique()
                ->values()
                ->all(),
            'sem_centro_custo' => $linhas->contains(fn($l) => $l->CD_CENTRO_CUSTO === null),
        ];
    }

    private function table(string $nome): string
    {
        return sprintf(
            '%s.%s.%s',
            config('questor.database', 'FUNCSIDERURG'),
            config('questor.schema', 'dbo'),
            $nome
        );
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, object>
     *
     * @throws QuestorException
     */
    private function select(string $sql, array $bindings): array
    {
        QuestorGate::ensureEnabled();

        try {
            return DB::connection(config('questor.connection'))->select($sql, $bindings);
        } catch (\Throwable $e) {
            report($e);

            throw new QuestorException(
                'Não foi possível consultar os centros de custo no Questor.'
            );
        }
    }
}

<?php

namespace App\Services\Questor;

use App\Services\Questor\DTO\SolicitacaoDTO;
use App\Services\Questor\DTO\SolicitacaoItemDTO;
use Illuminate\Support\Collection;

/**
 * Leitura das Solicitações de Compra do Questor — a origem do mapa de cotação.
 *
 * Queries 1 (cabeçalho), 1.b (busca por período/solicitante) e 2 (itens) do
 * arquivo `mapa_cotacao_questor_queries.sql`.
 *
 * SÓ LEITURA. Ver {@see QuestorReadRepository}.
 */
class QuestorSolicitacaoRepository extends QuestorReadRepository
{
    /**
     * Cabeçalho da solicitação (query 1).
     *
     * Devolve `null` quando não existe — e não uma exceção: "solicitação 34334
     * não encontrada" é uma resposta esperada da tela de busca, não uma falha.
     *
     * Todos os joins de apoio são LEFT: o status pode não existir em
     * TBL_STATUS, o departamento não tem FK declarada, e nenhum deles pode
     * fazer a solicitação sumir da tela.
     */
    public function buscar(int $cdSolicitacao): ?SolicitacaoDTO
    {
        $sql = sprintf(
            'SELECT TOP (1)
                    s.CD_SOLICITACAO,
                    s.CD_EMPRESA,
                    s.CD_FILIAL,
                    fil.DS_FILIAL,
                    s.DS_SOLICITANTE,
                    s.CD_DEPARTAMENTO,
                    dep.DS_DEPARTAMENTO,
                    s.DT_CADASTRO,
                    s.DT_FINALIZACAO,
                    s.DS_OBS,
                    s.CD_STATUS,
                    st.DS_STATUS,
                    s.CD_OBRA,
                    obr.DS_OBRA,
                    usu_cad.DS_USUARIO AS USUARIO_CADASTRO,
                    QTD_ITENS = (SELECT COUNT(*) FROM %s it
                                 WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO)
             FROM   %s s
             LEFT JOIN %s fil ON fil.CD_FILIAL = s.CD_FILIAL
             LEFT JOIN %s st  ON st.CD_STATUS = s.CD_STATUS
             LEFT JOIN %s dep ON dep.CD_DEPARTAMENTO = s.CD_DEPARTAMENTO
             LEFT JOIN %s obr ON obr.CD_OBRA = s.CD_OBRA
             LEFT JOIN %s usu_cad ON usu_cad.CD_CODUSUARIO = s.CD_USUARIO
             WHERE  s.CD_SOLICITACAO = ?',
            $this->table('TBL_COMPRAS_SOLICITACAO_ITENS'),
            $this->table('TBL_COMPRAS_SOLICITACAO'),
            $this->table('TBL_EMPRESAS_FILIAIS'),
            $this->table('TBL_STATUS'),
            $this->table('TBL_DEPARTAMENTOS'),
            $this->table('TBL_OBRAS'),
            $this->table('TBL_USUARIOS'),
        );

        $linha = $this->select($sql, [$cdSolicitacao])[0] ?? null;

        return $linha ? SolicitacaoDTO::deLinha($linha) : null;
    }

    /**
     * Busca por período e texto (query 1.b) — para quem não sabe o número da SC.
     *
     * O texto procura em solicitante, observação e descrição dos itens. É a
     * terceira que costuma achar: quem pede a cotação lembra "as tintas do
     * parquinho", não o número.
     *
     * @return Collection<int, SolicitacaoDTO>
     */
    public function procurar(?string $dataInicio, ?string $dataFim, ?string $busca = null, int $limite = 200): Collection
    {
        $where = [];
        $bindings = [];

        if (filled($dataInicio)) {
            $where[] = 's.DT_CADASTRO >= ?';
            $bindings[] = $dataInicio;
        }

        if (filled($dataFim)) {
            // DATEADD para incluir o dia inteiro: DT_CADASTRO é datetime, e
            // `<= '2026-12-31'` perderia tudo o que foi cadastrado depois da
            // meia-noite daquele dia.
            $where[] = 's.DT_CADASTRO < DATEADD(day, 1, ?)';
            $bindings[] = $dataFim;
        }

        $busca = trim((string) $busca);

        if ($busca !== '') {
            $where[] = sprintf(
                '(s.DS_SOLICITANTE LIKE ? OR s.DS_OBS LIKE ?
                  OR EXISTS (SELECT 1 FROM %s it
                             WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO
                               AND it.DS_MATERIAL LIKE ?))',
                $this->table('TBL_COMPRAS_SOLICITACAO_ITENS')
            );

            $curinga = '%' . $busca . '%';
            array_push($bindings, $curinga, $curinga, $curinga);
        }

        $sql = sprintf(
            'SELECT TOP (%d)
                    s.CD_SOLICITACAO,
                    s.CD_EMPRESA,
                    s.CD_FILIAL,
                    fil.DS_FILIAL,
                    s.DS_SOLICITANTE,
                    s.CD_DEPARTAMENTO,
                    dep.DS_DEPARTAMENTO,
                    s.DT_CADASTRO,
                    s.DT_FINALIZACAO,
                    s.DS_OBS,
                    s.CD_STATUS,
                    st.DS_STATUS,
                    QTD_ITENS = (SELECT COUNT(*) FROM %s it
                                 WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO)
             FROM   %s s
             LEFT JOIN %s fil ON fil.CD_FILIAL = s.CD_FILIAL
             LEFT JOIN %s dep ON dep.CD_DEPARTAMENTO = s.CD_DEPARTAMENTO
             LEFT JOIN %s st  ON st.CD_STATUS = s.CD_STATUS
             %s
             ORDER BY s.CD_SOLICITACAO DESC',
            max(1, min($limite, 200)),
            $this->table('TBL_COMPRAS_SOLICITACAO_ITENS'),
            $this->table('TBL_COMPRAS_SOLICITACAO'),
            $this->table('TBL_EMPRESAS_FILIAIS'),
            $this->table('TBL_DEPARTAMENTOS'),
            $this->table('TBL_STATUS'),
            $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        );

        return collect($this->select($sql, $bindings))
            ->map(fn (object $l) => SolicitacaoDTO::deLinha($l));
    }

    /**
     * Itens da solicitação (query 2) — as linhas do mapa.
     *
     * DS_MATERIAL do item ganha do cadastro: quando o solicitante escreveu a
     * descrição na mão, é a dele que descreve o que ele quer. O COALESCE só cai
     * no cadastro quando o item veio sem texto.
     *
     * @return Collection<int, SolicitacaoItemDTO>
     */
    public function itens(int $cdSolicitacao): Collection
    {
        $sql = sprintf(
            'SELECT i.CD_SOLICITACAO,
                    i.CD_ITEM,
                    i.CD_MATERIAL,
                    DS_MATERIAL = COALESCE(NULLIF(i.DS_MATERIAL, \'\'), mat.DS_MATERIAL),
                    DS_UNIDADE  = COALESCE(NULLIF(i.DS_UNIDADE, \'\'), uni.DS_ABREVIATURA),
                    i.NR_QUANTIDADE,
                    i.VL_UNITARIO,
                    i.VL_TOTAL,
                    i.DS_OBS,
                    i.CD_ALMOXARIFADO,
                    alm.DS_ALMOXARIFADO,
                    i.CD_CENTRO_CUSTO,
                    est.NR_ESTOQUE_DISPONIVEL,
                    est.VL_CUSTO_MEDIO,
                    est.DT_ULTIMA_COMPRA AS DT_ULTIMA_COMPRA_FILIAL,
                    est.VL_ULTIMA_COMPRA AS VL_ULTIMA_COMPRA_FILIAL
             FROM   %s i
             JOIN   %s s ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
             LEFT JOIN %s mat ON mat.CD_MATERIAL = i.CD_MATERIAL
             LEFT JOIN %s uni ON uni.CD_UNIDADE = mat.CD_UNIDADE
             LEFT JOIN %s alm ON alm.CD_ALMOXARIFADO = i.CD_ALMOXARIFADO
             LEFT JOIN %s est ON est.CD_MATERIAL = i.CD_MATERIAL
                             AND est.CD_FILIAL = s.CD_FILIAL
             WHERE  i.CD_SOLICITACAO = ?
             ORDER BY i.CD_ITEM',
            // A string vazia do NULLIF fica literal no SQL, e não como binding:
            // um `?` dentro de NULLIF vira parâmetro sem tipo definido, e o SQL
            // Server recusa.
            $this->table('TBL_COMPRAS_SOLICITACAO_ITENS'),
            $this->table('TBL_COMPRAS_SOLICITACAO'),
            $this->table('TBL_MATERIAIS'),
            $this->table('TBL_MATERIAIS_UNIDADE'),
            $this->table('TBL_MATERIAIS_ALMOXARIFADO'),
            $this->table('TBL_MATERIAIS_ESTOQUE'),
        );

        return collect($this->select($sql, [$cdSolicitacao]))
            ->map(fn (object $l) => SolicitacaoItemDTO::deLinha($l));
    }
}

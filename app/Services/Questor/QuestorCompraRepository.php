<?php

namespace App\Services\Questor;

use App\Services\Questor\DTO\FornecedorHistoricoDTO;
use App\Services\Questor\DTO\UltimaCompraDTO;
use Illuminate\Support\Collection;

/**
 * Histórico de compras do Questor: última compra, entradas anteriores,
 * fornecedores que já forneceram, homologados, cotações antigas e OCs abertas.
 *
 * Queries 3, 4, 5, 5.c, 6, 7.b e 8 do `mapa_cotacao_questor_queries.sql`.
 *
 * DUAS DECISÕES ATRAVESSAM TODAS AS CONSULTAS DAQUI:
 *
 * 1. O que conta como "compra" é `TBL_CME.X_ATUALIZA_DT_ULTIMA_COMPRA = 1` — o
 *    próprio flag do Questor. Ele exclui devolução, transferência, remessa e
 *    industrialização sem ninguém precisar manter uma lista de CFOP. O
 *    `ISNULL(..., 1)` mantém a entrada cujo CME não está cadastrado, porque na
 *    dúvida ela é compra: o default da coluna no ERP é 1.
 *
 * 2. `TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS` NÃO TEM FK. Todo join com
 *    TBL_STATUS é LEFT — com INNER, uma nota de status não cadastrado sumiria
 *    do histórico em silêncio, que é o pior jeito de errar um preço.
 *
 * SÓ LEITURA. Ver {@see QuestorReadRepository}.
 */
class QuestorCompraRepository extends QuestorReadRepository
{
    /**
     * A última compra de CADA item da solicitação (query 3).
     *
     * Uma consulta só, com OUTER APPLY, para a solicitação inteira. Não é
     * otimização prematura: a alternativa é uma consulta por item contra um ERP
     * remoto, e uma SC de 40 itens viraria 40 idas ao banco na abertura da
     * tela.
     *
     * OUTER APPLY e não CROSS APPLY: o item que nunca foi comprado — ou que nem
     * tem cadastro — precisa continuar aparecendo, com a última compra vazia.
     *
     * @return Collection<int, UltimaCompraDTO> indexada por CD_ITEM
     */
    public function ultimaCompraPorItem(int $cdSolicitacao): Collection
    {
        [$filtroStatus, $bindingsStatus] = $this->filtroNaoCancelada('e');

        // Restringir à filial da SC é decisão de operação (config): o preço de
        // uma tinta não muda por ela ter entrado noutra filial da mesma
        // empresa, e limitar demais faz o mapa nascer sem referência nenhuma.
        $mesmaFilial = (bool) config('questor.cotacao.somente_mesma_filial', false)
            ? ' AND e.CD_FILIAL = s.CD_FILIAL'
            : '';

        $sql = sprintf(
            'SELECT i.CD_ITEM,
                    i.CD_MATERIAL,
                    uc.DT_ENTRADA      AS ULT_COMPRA_DATA,
                    uc.NR_DOCUMENTO    AS ULT_COMPRA_NF,
                    uc.CD_FORNECEDOR   AS ULT_COMPRA_CD_FORNECEDOR,
                    uc.DS_ENTIDADE     AS ULT_COMPRA_FORNECEDOR,
                    uc.DS_FANTASIA     AS ULT_COMPRA_FORNECEDOR_FANTASIA,
                    uc.NR_CPFCNPJ      AS ULT_COMPRA_CNPJ,
                    uc.NR_QUANTIDADE   AS ULT_COMPRA_QTD,
                    uc.DS_UNIDADE      AS ULT_COMPRA_UN,
                    uc.VL_UNITARIO     AS ULT_COMPRA_VL_UNITARIO,
                    uc.VL_CUSTO_COMPRA AS ULT_COMPRA_VL_CUSTO,
                    uc.DS_CME          AS ULT_COMPRA_OPERACAO,
                    uc.CD_FILIAL       AS ULT_COMPRA_FILIAL,
                    VARIACAO_VS_CUSTO_MEDIO = CASE WHEN ISNULL(est.VL_CUSTO_MEDIO, 0) > 0
                                                   THEN (uc.VL_UNITARIO - est.VL_CUSTO_MEDIO) / est.VL_CUSTO_MEDIO
                                              END
             FROM   %s i
             JOIN   %s s ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
             LEFT JOIN %s est ON est.CD_MATERIAL = i.CD_MATERIAL
                             AND est.CD_FILIAL = s.CD_FILIAL
             OUTER APPLY (
                 SELECT TOP (1)
                        e.CD_ENTRADA, e.DT_ENTRADA, e.NR_DOCUMENTO,
                        e.CD_FORNECEDOR, e.CD_FILIAL,
                        ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                        ni.NR_QUANTIDADE, ni.DS_UNIDADE, ni.VL_UNITARIO,
                        ni.VL_CUSTO_COMPRA, ni.DS_CME
                 FROM   %s ni
                 JOIN   %s e   ON e.CD_ENTRADA = ni.CD_ENTRADA
                 JOIN   %s ent ON ent.CD_ENTIDADE = e.CD_FORNECEDOR
                 LEFT JOIN %s cme ON cme.CD_CME = ni.CD_CME
                 WHERE  ni.CD_MATERIAL = i.CD_MATERIAL
                   AND  e.CD_EMPRESA = s.CD_EMPRESA%s%s
                   AND  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
                   AND  ni.VL_UNITARIO > 0
                 ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC, ni.CD_ITEM DESC
             ) uc
             WHERE  i.CD_SOLICITACAO = ?
             ORDER BY i.CD_ITEM',
            $this->table('TBL_COMPRAS_SOLICITACAO_ITENS'),
            $this->table('TBL_COMPRAS_SOLICITACAO'),
            $this->table('TBL_MATERIAIS_ESTOQUE'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA'),
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_CME'),
            $mesmaFilial,
            $filtroStatus,
        );

        // Ordem dos bindings segue a ordem TEXTUAL dos `?`: o filtro de status
        // está dentro do OUTER APPLY, que vem antes do WHERE de fora.
        $bindings = [...$bindingsStatus, $cdSolicitacao];

        return collect($this->select($sql, $bindings))
            ->map(fn (object $l) => UltimaCompraDTO::deLinha($l))
            ->keyBy(fn (UltimaCompraDTO $dto) => $dto->item);
    }

    /**
     * Histórico de entradas de um material (query 4) — o drill-down do item.
     *
     * @return Collection<int, object>
     */
    public function historicoDoMaterial(int $cdMaterial): Collection
    {
        [$filtroStatus, $bindingsStatus] = $this->filtroNaoCancelada('e');

        $meses = max(1, (int) config('questor.cotacao.meses_historico', 24));
        $limite = max(1, (int) config('questor.cotacao.limite_historico', 20));

        $sql = sprintf(
            'SELECT TOP (%d)
                    e.CD_ENTRADA, e.DT_ENTRADA, e.DT_EMISSAO, e.NR_DOCUMENTO, e.DS_SERIE,
                    e.CD_FILIAL, fil.DS_FILIAL,
                    e.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                    cid.DS_CIDADE, cid.DS_UF,
                    ni.CD_ITEM, ni.DS_MATERIAL, ni.DS_UNIDADE, ni.NR_QUANTIDADE,
                    ni.VL_UNITARIO, ni.VL_DESCONTO, ni.VL_IPI, ni.VL_TOTAL, ni.VL_CUSTO_COMPRA,
                    ni.CD_CME, ni.DS_CME,
                    fpg.DS_FORMA_PAGAMENTO, frt.DS_FRETE,
                    e.CD_ORDEM_COMPRA, e.CD_STATUS, stn.DS_STATUS
             FROM   %s ni
             JOIN   %s e   ON e.CD_ENTRADA = ni.CD_ENTRADA
             JOIN   %s ent ON ent.CD_ENTIDADE = e.CD_FORNECEDOR
             LEFT JOIN %s cid ON cid.CD_CIDADE = ent.CD_CIDADE
             LEFT JOIN %s fil ON fil.CD_FILIAL = e.CD_FILIAL
             LEFT JOIN %s fpg ON fpg.CD_FORMA_PAGAMENTO = e.CD_FORMA_PAGAMENTO
             LEFT JOIN %s frt ON frt.CD_FRETE = e.CD_FRETE
             LEFT JOIN %s cme ON cme.CD_CME = ni.CD_CME
             LEFT JOIN %s stn ON stn.CD_STATUS = e.CD_STATUS
             WHERE  ni.CD_MATERIAL = ?
               AND  e.DT_ENTRADA >= DATEADD(month, -%d, GETDATE())%s
               AND  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
             ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC',
            $limite,
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA'),
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_ENDERECO_CIDADES'),
            $this->table('TBL_EMPRESAS_FILIAIS'),
            $this->table('TBL_FINANCEIRO_FORMAS_PAGAMENTO'),
            $this->table('TBL_FRETES'),
            $this->table('TBL_CME'),
            $this->table('TBL_STATUS'),
            $meses,
            $filtroStatus,
        );

        return collect($this->selectCached(
            "hist.material.{$cdMaterial}.{$meses}.{$limite}",
            $sql,
            [$cdMaterial, ...$bindingsStatus]
        ));
    }

    /**
     * Fornecedores que já forneceram um material (query 5.b — a versão em CTE).
     *
     * A do enunciado (5) e esta dão a mesma saída; a CTE tem plano mais
     * previsível em volume grande, e é a que roda no drill-down.
     *
     * @return Collection<int, FornecedorHistoricoDTO>
     */
    public function fornecedoresDoMaterial(int $cdMaterial): Collection
    {
        [$filtroStatus, $bindingsStatus] = $this->filtroNaoCancelada('e');

        $sql = sprintf(
            'WITH COMPRAS AS (
                 SELECT e.CD_ENTRADA, e.DT_ENTRADA, e.CD_FORNECEDOR,
                        ni.NR_QUANTIDADE, ni.VL_UNITARIO, ni.VL_TOTAL, ni.DS_UNIDADE,
                        RN = ROW_NUMBER() OVER (PARTITION BY e.CD_FORNECEDOR
                                                ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC)
                 FROM   %s ni
                 JOIN   %s e ON e.CD_ENTRADA = ni.CD_ENTRADA
                 LEFT JOIN %s cme ON cme.CD_CME = ni.CD_CME
                 WHERE  ni.CD_MATERIAL = ?%s
                   AND  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
                   AND  ni.VL_UNITARIO > 0
             )
             SELECT c.CD_FORNECEDOR,
                    ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                    ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO,
                    cid.DS_CIDADE, cid.DS_UF,
                    QTD_COMPRAS    = COUNT(*),
                    DT_ULTIMA      = MAX(c.DT_ENTRADA),
                    VL_UNIT_MEDIO  = AVG(c.VL_UNITARIO),
                    VL_UNIT_MIN    = MIN(c.VL_UNITARIO),
                    VL_UNIT_ULTIMO = MAX(CASE WHEN c.RN = 1 THEN c.VL_UNITARIO END)
             FROM   COMPRAS c
             JOIN   %s ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
             LEFT JOIN %s cid ON cid.CD_CIDADE = ent.CD_CIDADE
             GROUP BY c.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                      ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO,
                      cid.DS_CIDADE, cid.DS_UF
             ORDER BY DT_ULTIMA DESC',
            // A ordem segue a dos `%s` NO TEXTO da consulta, e o filtro de
            // status está dentro da CTE — antes, portanto, das duas últimas
            // tabelas.
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA'),
            $this->table('TBL_CME'),
            $filtroStatus,
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_ENDERECO_CIDADES'),
        );

        return collect($this->selectCached(
            "forn.material.{$cdMaterial}",
            $sql,
            [$cdMaterial, ...$bindingsStatus]
        ))->map(fn (object $l) => FornecedorHistoricoDTO::deLinha($l));
    }

    /**
     * Fornecedores de TODOS os itens da solicitação de uma vez (query 5.c).
     *
     * É o que alimenta a sugestão de colunas na importação. Uma consulta para a
     * SC inteira, e não uma por item, pelo mesmo motivo da query 3.
     *
     * Só entram itens com CD_MATERIAL: os de texto livre não têm como ter
     * histórico por código, e a CTE já os descarta.
     *
     * @return Collection<int, FornecedorHistoricoDTO>
     */
    public function fornecedoresDaSolicitacao(int $cdSolicitacao): Collection
    {
        [$filtroStatus, $bindingsStatus] = $this->filtroNaoCancelada('e');

        $sql = sprintf(
            'WITH ITENS AS (
                 SELECT i.CD_ITEM, i.CD_MATERIAL, s.CD_EMPRESA, s.CD_FILIAL
                 FROM   %s i
                 JOIN   %s s ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
                 WHERE  i.CD_SOLICITACAO = ?
                   AND  i.CD_MATERIAL IS NOT NULL
             ), COMPRAS AS (
                 SELECT it.CD_ITEM, it.CD_MATERIAL, e.CD_FORNECEDOR,
                        e.DT_ENTRADA, ni.VL_UNITARIO, ni.DS_UNIDADE, ni.NR_QUANTIDADE,
                        RN = ROW_NUMBER() OVER (PARTITION BY it.CD_ITEM, e.CD_FORNECEDOR
                                                ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC)
                 FROM   ITENS it
                 JOIN   %s ni ON ni.CD_MATERIAL = it.CD_MATERIAL
                 JOIN   %s e  ON e.CD_ENTRADA = ni.CD_ENTRADA
                             AND e.CD_EMPRESA = it.CD_EMPRESA
                 LEFT JOIN %s cme ON cme.CD_CME = ni.CD_CME
                 WHERE  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
                   AND  ni.VL_UNITARIO > 0%s
             )
             SELECT c.CD_ITEM, c.CD_MATERIAL, c.CD_FORNECEDOR,
                    ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                    ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO,
                    QTD_COMPRAS    = COUNT(*),
                    DT_ULTIMA      = MAX(c.DT_ENTRADA),
                    VL_UNIT_ULTIMO = MAX(CASE WHEN c.RN = 1 THEN c.VL_UNITARIO END),
                    VL_UNIT_MEDIO  = AVG(c.VL_UNITARIO)
             FROM   COMPRAS c
             JOIN   %s ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
             GROUP BY c.CD_ITEM, c.CD_MATERIAL, c.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA,
                      ent.NR_CPFCNPJ, ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO
             ORDER BY c.CD_ITEM, DT_ULTIMA DESC',
            $this->table('TBL_COMPRAS_SOLICITACAO_ITENS'),
            $this->table('TBL_COMPRAS_SOLICITACAO'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA'),
            $this->table('TBL_CME'),
            $filtroStatus,
            $this->table('TBL_ENTIDADES'),
        );

        return collect($this->selectCached(
            "forn.sc.{$cdSolicitacao}",
            $sql,
            [$cdSolicitacao, ...$bindingsStatus]
        ))->map(fn (object $l) => FornecedorHistoricoDTO::deLinha($l));
    }

    /**
     * Fornecedores HOMOLOGADOS no cadastro do material (query 6).
     *
     * Diferente da 5: aqui é quem PODE fornecer, mesmo sem nunca ter fornecido.
     * As duas listas se complementam no drill-down — histórico responde "quem
     * já vendeu", homologação responde "de quem posso comprar".
     *
     * @return Collection<int, object>
     */
    public function fornecedoresHomologados(int $cdMaterial): Collection
    {
        $sql = sprintf(
            'SELECT mf.CD_MATERIAL, mf.CD_FORNECEDOR,
                    ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                    ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO,
                    mf.CD_MATERIAL_FORNECEDOR AS COD_PRODUTO_NO_FORNECEDOR,
                    mf.DS_NOME_PRODUTO_FORNECEDOR,
                    mf.DT_CADASTRO
             FROM   %s mf
             JOIN   %s ent ON ent.CD_ENTIDADE = mf.CD_FORNECEDOR
             WHERE  mf.CD_MATERIAL = ?
             ORDER BY ent.DS_ENTIDADE',
            $this->table('TBL_MATERIAIS_FORNECEDOR'),
            $this->table('TBL_ENTIDADES'),
        );

        return collect($this->selectCached("homol.material.{$cdMaterial}", $sql, [$cdMaterial]));
    }

    /**
     * Cotações anteriores do material já registradas no próprio Questor
     * (query 7.b).
     *
     * @return Collection<int, object>
     */
    public function cotacoesAnteriores(int $cdMaterial, int $limite = 30): Collection
    {
        $sql = sprintf(
            'SELECT TOP (%d)
                    c.CD_COTACAO, c.DT_EMISSAO, c.CD_FORNECEDOR,
                    ent.DS_ENTIDADE, ent.DS_FANTASIA,
                    ci.DS_MATERIAL, ci.DS_UNIDADE, ci.NR_QUANTIDADE,
                    ci.VL_UNITARIO, ci.VL_TOTAL, ci.VL_FRETE, ci.DS_OBS
             FROM   %s ci
             JOIN   %s c   ON c.CD_COTACAO = ci.CD_COTACAO
             JOIN   %s ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
             WHERE  ci.CD_MATERIAL = ?
             ORDER BY c.DT_EMISSAO DESC',
            max(1, min($limite, 100)),
            $this->table('TBL_COMPRAS_COTACAO_ITENS'),
            $this->table('TBL_COMPRAS_COTACAO'),
            $this->table('TBL_ENTIDADES'),
        );

        return collect($this->selectCached("cot.material.{$cdMaterial}.{$limite}", $sql, [$cdMaterial]));
    }

    /**
     * Ordens de compra do material (query 8) — compra pedida e ainda não
     * faturada.
     *
     * Complementa a "última compra": um preço acertado semana passada só vira
     * NF de entrada quando a mercadoria chega, e até lá ele só existe aqui.
     *
     * @return Collection<int, object>
     */
    public function ordensDoMaterial(int $cdMaterial, int $limite = 20): Collection
    {
        $sql = sprintf(
            'SELECT TOP (%d)
                    oc.CD_ORDEM_COMPRA, oc.DT_EMISSAO, oc.DT_ENTREGA_PREVISTA,
                    oc.CD_ENTIDADE AS CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA,
                    oci.DS_MATERIAL, oci.DS_UNIDADE, oci.NR_QUANTIDADE, oci.NR_SALDO,
                    oci.VL_UNITARIO, oci.VL_TOTAL, oci.DT_ENTREGA,
                    oc.CD_STATUS, st.DS_STATUS, oc.DT_AUTORIZACAO,
                    frt.DS_FRETE, pze.DS_PRAZO_ENTREGA, fpg.DS_FORMA_PAGAMENTO
             FROM   %s oci
             JOIN   %s oc  ON oc.CD_ORDEM_COMPRA = oci.CD_ORDEM_COMPRA
             JOIN   %s ent ON ent.CD_ENTIDADE = oc.CD_ENTIDADE
             LEFT JOIN %s st  ON st.CD_STATUS = oc.CD_STATUS
             LEFT JOIN %s frt ON frt.CD_FRETE = oc.CD_FRETE
             LEFT JOIN %s pze ON pze.CD_PRAZO_ENTREGA = oc.CD_PRAZO_ENTREGA
             LEFT JOIN %s fpg ON fpg.CD_FORMA_PAGAMENTO = oc.CD_FORMA_PAGAMENTO
             WHERE  oci.CD_MATERIAL = ?
             ORDER BY oc.DT_EMISSAO DESC',
            max(1, min($limite, 100)),
            $this->table('TBL_COMPRAS_ORDEM_COMPRA_ITENS'),
            $this->table('TBL_COMPRAS_ORDEM_COMPRA'),
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_STATUS'),
            $this->table('TBL_FRETES'),
            $this->table('TBL_PRAZO_ENTREGA'),
            $this->table('TBL_FINANCEIRO_FORMAS_PAGAMENTO'),
        );

        return collect($this->selectCached("oc.material.{$cdMaterial}.{$limite}", $sql, [$cdMaterial]));
    }
}

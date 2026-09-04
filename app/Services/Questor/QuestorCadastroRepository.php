<?php

namespace App\Services\Questor;

use Illuminate\Support\Collection;

/**
 * Cadastros de apoio do Questor: fretes, prazos, formas de pagamento,
 * autocomplete de fornecedor e de material.
 *
 * Queries 9.a a 9.e do `mapa_cotacao_questor_queries.sql`, mais as duas de
 * descoberta (0.1b e 0.2) que alimentam `php artisan cotacao:descobrir-config`.
 *
 * IMPORTANTE sobre 9.a–9.c: estas listas são AUTOCOMPLETE, não restrição. O
 * mapa em uso hoje tem prazo "CONFIRMAR" e "3DU" e condição "Á VISTA" — valores
 * que não existem em TBL_PRAZO_ENTREGA nem em TBL_FINANCEIRO_FORMAS_PAGAMENTO.
 * Transformar estas tabelas em combo obrigatório impediria o comprador de
 * registrar o que o fornecedor de fato falou ao telefone.
 *
 * SÓ LEITURA. Ver {@see QuestorReadRepository}.
 */
class QuestorCadastroRepository extends QuestorReadRepository
{
    /**
     * Modalidades de frete — CIF, FOB... (query 9.a). Linha 5 do XLSX.
     *
     * @return Collection<int, object>
     */
    public function fretes(): Collection
    {
        $sql = sprintf(
            'SELECT CD_FRETE, DS_FRETE, NR_MODALIDADE_NFE FROM %s ORDER BY CD_FRETE',
            $this->table('TBL_FRETES')
        );

        return collect($this->selectCached('fretes', $sql));
    }

    /**
     * Prazos de entrega cadastrados (query 9.b). Linha 6 do XLSX.
     *
     * @return Collection<int, object>
     */
    public function prazosEntrega(): Collection
    {
        $sql = sprintf(
            'SELECT CD_PRAZO_ENTREGA, DS_PRAZO_ENTREGA, NR_DIAS FROM %s ORDER BY NR_DIAS',
            $this->table('TBL_PRAZO_ENTREGA')
        );

        return collect($this->selectCached('prazos', $sql));
    }

    /**
     * Formas de pagamento de compra ativas (query 9.c). Linha 7 do XLSX.
     *
     * @return Collection<int, object>
     */
    public function formasPagamento(): Collection
    {
        $sql = sprintf(
            'SELECT CD_FORMA_PAGAMENTO, DS_FORMA_PAGAMENTO, DS_ABREVIACAO
             FROM   %s
             WHERE  X_ATIVO = 1 AND X_SOMENTE_COMPRA = 1
             ORDER  BY DS_FORMA_PAGAMENTO',
            $this->table('TBL_FINANCEIRO_FORMAS_PAGAMENTO')
        );

        return collect($this->selectCached('formas_pagamento', $sql));
    }

    /**
     * Autocomplete de fornecedor (query 9.d) — para o comprador acrescentar uma
     * coluna ao mapa.
     *
     * `X_FORNECEDOR = 1` é o que distingue fornecedor em TBL_ENTIDADES, que é
     * o cadastro único de todas as partes (cliente, transportador, vendedor,
     * funcionário) e não uma tabela de fornecedores.
     *
     * Sem cache: é digitação ao vivo, e cada termo é uma chave diferente — o
     * cache só encheria de lixo.
     *
     * @return Collection<int, object>
     */
    public function buscarFornecedores(string $busca, int $limite = 30): Collection
    {
        $busca = trim($busca);

        if (mb_strlen($busca) < 2) {
            return collect();
        }

        $sql = sprintf(
            'SELECT TOP (%d)
                    ent.CD_ENTIDADE, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
                    ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA,
                    cid.DS_CIDADE, cid.DS_UF
             FROM   %s ent
             LEFT JOIN %s cid ON cid.CD_CIDADE = ent.CD_CIDADE
             WHERE  ent.X_FORNECEDOR = 1
               AND  ent.X_ATIVO = 1
               AND  (ent.DS_ENTIDADE LIKE ?
                     OR ent.DS_FANTASIA LIKE ?
                     OR ent.NR_CPFCNPJ_SEM_FORMATO LIKE ?)
             ORDER BY ent.DS_ENTIDADE',
            max(1, min($limite, 50)),
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_ENDERECO_CIDADES'),
        );

        // CNPJ casa por prefixo (o comprador digita o começo do número);
        // nome e fantasia casam em qualquer posição.
        $curinga = '%' . $busca . '%';
        $somenteDigitos = preg_replace('/\D+/', '', $busca);

        return collect($this->select($sql, [$curinga, $curinga, $somenteDigitos . '%']));
    }

    /**
     * Autocomplete de material (query 9.e) — para o item avulso acrescentado ao
     * mapa.
     *
     * @return Collection<int, object>
     */
    public function buscarMateriais(string $busca, int $limite = 30): Collection
    {
        $busca = trim($busca);

        if (mb_strlen($busca) < 2) {
            return collect();
        }

        $sql = sprintf(
            'SELECT TOP (%d)
                    m.CD_MATERIAL, m.DS_MATERIAL, m.CD_REFERENCIA, m.CD_NCM,
                    un.DS_ABREVIATURA AS DS_UNIDADE, m.X_ATIVO
             FROM   %s m
             LEFT JOIN %s un ON un.CD_UNIDADE = m.CD_UNIDADE
             WHERE  m.X_ATIVO = 1
               AND  (m.DS_MATERIAL LIKE ? OR m.CD_REFERENCIA = ?)
             ORDER BY m.DS_MATERIAL',
            max(1, min($limite, 50)),
            $this->table('TBL_MATERIAIS'),
            $this->table('TBL_MATERIAIS_UNIDADE'),
        );

        return collect($this->select($sql, ['%' . $busca . '%', $busca]));
    }

    /**
     * DESCOBERTA (query 0.1b): quais CD_STATUS realmente aparecem nas notas de
     * entrada, inclusive os que nem existem em TBL_STATUS.
     *
     * É daqui que sai o valor de `QUESTOR_STATUS_NF_CANCELADA`. O LEFT JOIN é
     * o ponto da consulta: a coluna não tem FK, e um INNER esconderia
     * justamente os status órfãos que se quer enxergar.
     *
     * @return Collection<int, object>
     */
    public function statusDasNotasEntrada(): Collection
    {
        $sql = sprintf(
            'SELECT e.CD_STATUS, st.DS_STATUS, COUNT(*) AS QTD,
                    MIN(e.DT_ENTRADA) AS DT_MIN, MAX(e.DT_ENTRADA) AS DT_MAX
             FROM   %s e
             LEFT JOIN %s st ON st.CD_STATUS = e.CD_STATUS
             GROUP BY e.CD_STATUS, st.DS_STATUS
             ORDER BY QTD DESC',
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA'),
            $this->table('TBL_STATUS'),
        );

        return collect($this->select($sql));
    }

    /**
     * DESCOBERTA (query 0.2): quais operações (CME) aparecem nos itens de
     * entrada e quais delas o Questor considera compra.
     *
     * Serve para conferir, antes de fechar o filtro, que
     * `X_ATUALIZA_DT_ULTIMA_COMPRA` de fato separa compra de devolução,
     * transferência e remessa nesta base.
     *
     * @return Collection<int, object>
     */
    public function operacoesEmUso(): Collection
    {
        $sql = sprintf(
            'SELECT c.CD_CME, c.DS_CME, c.CD_CFOP_CONTRIBUINTE,
                    c.X_ESTOQUE, c.X_CUSTO_MEDIO, c.X_ATUALIZA_DT_ULTIMA_COMPRA,
                    QTD_ITENS = COUNT(i.CD_ID)
             FROM   %s c
             LEFT JOIN %s i ON i.CD_CME = c.CD_CME
             GROUP BY c.CD_CME, c.DS_CME, c.CD_CFOP_CONTRIBUINTE, c.X_ESTOQUE,
                      c.X_CUSTO_MEDIO, c.X_ATUALIZA_DT_ULTIMA_COMPRA
             HAVING COUNT(i.CD_ID) > 0
             ORDER BY QTD_ITENS DESC',
            $this->table('TBL_CME'),
            $this->table('TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS'),
        );

        return collect($this->select($sql));
    }
}

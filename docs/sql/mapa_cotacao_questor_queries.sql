/* ============================================================================
   MAPA DE COTAÇÃO — QUERIES BASE (SQL Server / FUNCSIDERURG.dbo — ERP Questor)
   ----------------------------------------------------------------------------
   Origem dos dados (somente leitura no Questor):
     TBL_COMPRAS_SOLICITACAO            (PK CD_SOLICITACAO)  -> cabeçalho da SC
     TBL_COMPRAS_SOLICITACAO_ITENS      (PK CD_SOLICITACAO, CD_ITEM)
     TBL_COMPRAS_NOTAFISCAL_ENTRADA     (PK CD_ENTRADA)      -> compra efetivada
     TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS (PK CD_ENTRADA, CD_ITEM)
     TBL_ENTIDADES                      (PK CD_ENTIDADE)     -> fornecedor
     TBL_MATERIAIS / TBL_MATERIAIS_ESTOQUE / TBL_MATERIAIS_FORNECEDOR
     TBL_COMPRAS_COTACAO / _ITENS       -> cotações já feitas dentro do Questor
     TBL_COMPRAS_ORDEM_COMPRA / _ITENS  -> OC (compra pedida, não faturada)
     TBL_CME                            -> motor de regra fiscal da operação

   PONTOS QUE PRECISAM SER DESCOBERTOS EM RUNTIME (não hardcodar):
     - CD_STATUS: TBL_COMPRAS_SOLICITACAO tem FK para TBL_STATUS, mas
       TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS (DEFAULT 2) NÃO tem FK declarada.
       Sempre LEFT JOIN. Rode a query 0.1 para achar o código de "Cancelada".
     - CD_CME: define se a entrada é compra, devolução, transferência, remessa...
       TBL_CME.X_ATUALIZA_DT_ULTIMA_COMPRA (bit, default 1) é o próprio flag que o
       Questor usa para dizer "esta operação conta como compra". Rode a query 0.2.

   CONVENÇÃO DE PARÂMETROS: @NOME (sp_executesql / bindings do Laravel).
   ========================================================================== */


/* ============================================================================
   0. QUERIES DE DESCOBERTA (rodar 1x, guardar o resultado em config)
   ========================================================================== */

-- 0.1 Status em uso pelas notas de entrada e pelas solicitações
SELECT  s.CD_STATUS,
        s.DS_STATUS,
        qtd_entradas     = (SELECT COUNT(*) FROM dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e
                            WHERE e.CD_STATUS = s.CD_STATUS),
        qtd_solicitacoes = (SELECT COUNT(*) FROM dbo.TBL_COMPRAS_SOLICITACAO c
                            WHERE c.CD_STATUS = s.CD_STATUS)
FROM    dbo.TBL_STATUS s
ORDER BY qtd_entradas DESC, qtd_solicitacoes DESC;

-- 0.1b Status realmente presentes nas entradas (inclusive os sem cadastro em TBL_STATUS)
SELECT  e.CD_STATUS, st.DS_STATUS, COUNT(*) AS QTD,
        MIN(e.DT_ENTRADA) AS DT_MIN, MAX(e.DT_ENTRADA) AS DT_MAX
FROM    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e
LEFT JOIN dbo.TBL_STATUS st ON st.CD_STATUS = e.CD_STATUS
GROUP BY e.CD_STATUS, st.DS_STATUS
ORDER BY QTD DESC;

-- 0.2 Quais CME aparecem nos itens de entrada e quais contam como "compra"
SELECT  c.CD_CME,
        c.DS_CME,
        c.CD_CFOP_CONTRIBUINTE,
        c.X_ESTOQUE,
        c.X_CUSTO_MEDIO,
        c.X_ATUALIZA_DT_ULTIMA_COMPRA,
        QTD_ITENS = COUNT(i.CD_ID)
FROM    dbo.TBL_CME c
LEFT JOIN dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS i ON i.CD_CME = c.CD_CME
GROUP BY c.CD_CME, c.DS_CME, c.CD_CFOP_CONTRIBUINTE, c.X_ESTOQUE,
         c.X_CUSTO_MEDIO, c.X_ATUALIZA_DT_ULTIMA_COMPRA
HAVING  COUNT(i.CD_ID) > 0
ORDER BY QTD_ITENS DESC;

-- 0.3 Empresas/filiais disponíveis (escopo do mapa)
SELECT f.CD_EMPRESA, f.CD_FILIAL, f.DS_FILIAL
FROM   dbo.TBL_EMPRESAS_FILIAIS f
ORDER  BY f.CD_EMPRESA, f.CD_FILIAL;


/* ============================================================================
   1. BUSCAR A SOLICITAÇÃO PELO CÓDIGO (cabeçalho do mapa)
   ========================================================================== */
DECLARE @CD_SOLICITACAO int = 34334;

SELECT  s.CD_SOLICITACAO,
        s.CD_EMPRESA,
        s.CD_FILIAL,
        fil.DS_FILIAL,
        s.DS_SOLICITANTE,                         -- varchar(30): texto livre
        dep.DS_DEPARTAMENTO,                      -- CD_DEPARTAMENTO (sem FK declarada)
        s.CD_DEPARTAMENTO,
        s.DT_CADASTRO,
        s.DT_FINALIZACAO,
        s.DS_OBS,
        s.CD_STATUS,
        st.DS_STATUS,
        s.X_CONFERIDO,
        s.CD_ORCAMENTO,
        s.CD_OBRA,
        obr.DS_OBRA,
        s.CD_FILIAL_ORIGEM,
        s.CD_FILIAL_DESTINO,
        usu_cad.DS_USUARIO   AS USUARIO_CADASTRO,
        usu_alt.DS_USUARIO   AS USUARIO_ALTERACAO,
        s.NR_REGISTRO_BLOQUEADO_EDICAO,
        s.CD_USUARIO_EDITANDO_REGISTRO,
        QTD_ITENS = (SELECT COUNT(*) FROM dbo.TBL_COMPRAS_SOLICITACAO_ITENS it
                     WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO)
FROM    dbo.TBL_COMPRAS_SOLICITACAO s
LEFT JOIN dbo.TBL_EMPRESAS_FILIAIS fil ON fil.CD_FILIAL = s.CD_FILIAL
LEFT JOIN dbo.TBL_STATUS          st  ON st.CD_STATUS  = s.CD_STATUS   -- LEFT: status pode não existir
LEFT JOIN dbo.TBL_DEPARTAMENTOS   dep ON dep.CD_DEPARTAMENTO = s.CD_DEPARTAMENTO
LEFT JOIN dbo.TBL_OBRAS           obr ON obr.CD_OBRA    = s.CD_OBRA
LEFT JOIN dbo.TBL_USUARIOS        usu_cad ON usu_cad.CD_CODUSUARIO = s.CD_USUARIO
LEFT JOIN dbo.TBL_USUARIOS        usu_alt ON usu_alt.CD_CODUSUARIO = s.CD_USUARIOAT
WHERE   s.CD_SOLICITACAO = @CD_SOLICITACAO;


/* ----------------------------------------------------------------------------
   1.b LISTA / AUTOCOMPLETE DE SOLICITAÇÕES (tela de busca)
   -------------------------------------------------------------------------- */
DECLARE @DT_INI date = '2026-01-01',
        @DT_FIM date = '2026-12-31',
        @BUSCA  varchar(100) = NULL;   -- solicitante, obs ou descrição de item

SELECT TOP (200)
        s.CD_SOLICITACAO,
        s.DT_CADASTRO,
        s.DS_SOLICITANTE,
        s.DS_OBS,
        s.CD_FILIAL,
        fil.DS_FILIAL,
        st.DS_STATUS,
        QTD_ITENS = (SELECT COUNT(*) FROM dbo.TBL_COMPRAS_SOLICITACAO_ITENS it
                     WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO)
FROM    dbo.TBL_COMPRAS_SOLICITACAO s
LEFT JOIN dbo.TBL_EMPRESAS_FILIAIS fil ON fil.CD_FILIAL = s.CD_FILIAL
LEFT JOIN dbo.TBL_STATUS          st  ON st.CD_STATUS  = s.CD_STATUS
WHERE   s.DT_CADASTRO >= @DT_INI
  AND   s.DT_CADASTRO <  DATEADD(day, 1, @DT_FIM)
  AND ( @BUSCA IS NULL
        OR s.DS_SOLICITANTE LIKE '%' + @BUSCA + '%'
        OR s.DS_OBS         LIKE '%' + @BUSCA + '%'
        OR EXISTS (SELECT 1 FROM dbo.TBL_COMPRAS_SOLICITACAO_ITENS it
                   WHERE it.CD_SOLICITACAO = s.CD_SOLICITACAO
                     AND it.DS_MATERIAL LIKE '%' + @BUSCA + '%') )
ORDER BY s.CD_SOLICITACAO DESC;


/* ============================================================================
   2. ITENS DA SOLICITAÇÃO (as linhas do mapa)
   ATENÇÃO: CD_MATERIAL é NULLABLE nesta tabela — item pode ser texto livre,
   sem cadastro em TBL_MATERIAIS. Nesse caso não há histórico de compra por código.
   ========================================================================== */
SELECT  i.CD_SOLICITACAO,
        i.CD_ITEM,
        i.CD_ID,
        i.CD_MATERIAL,
        DS_MATERIAL   = COALESCE(NULLIF(i.DS_MATERIAL, ''), mat.DS_MATERIAL),
        DS_UNIDADE    = COALESCE(NULLIF(i.DS_UNIDADE, ''), uni.DS_ABREVIATURA),
        i.NR_QUANTIDADE,
        i.VL_UNITARIO,                 -- referência digitada na SC (pode ser 0)
        i.VL_TOTAL,
        i.DS_OBS,
        i.CD_ALMOXARIFADO,
        alm.DS_ALMOXARIFADO,
        i.CD_CENTRO_CUSTO,
        i.CD_CONTA_GERENCIAL,
        i.CD_ORDEM_SERVICO,
        i.X_GEROU_COTACAO,
        i.NR_QUANTIDADE_ENVIADA,
        mat.CD_REFERENCIA,
        mat.CD_NCM,
        mat.X_ATIVO           AS MATERIAL_ATIVO,
        est.NR_ESTOQUE_DISPONIVEL,
        est.VL_CUSTO_MEDIO,
        est.DT_ULTIMA_COMPRA  AS DT_ULTIMA_COMPRA_FILIAL,   -- campo mantido pelo Questor
        est.VL_ULTIMA_COMPRA  AS VL_ULTIMA_COMPRA_FILIAL
FROM    dbo.TBL_COMPRAS_SOLICITACAO_ITENS i
JOIN    dbo.TBL_COMPRAS_SOLICITACAO s   ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
LEFT JOIN dbo.TBL_MATERIAIS mat         ON mat.CD_MATERIAL  = i.CD_MATERIAL
LEFT JOIN dbo.TBL_MATERIAIS_UNIDADE uni ON uni.CD_UNIDADE   = mat.CD_UNIDADE
LEFT JOIN dbo.TBL_MATERIAIS_ALMOXARIFADO alm ON alm.CD_ALMOXARIFADO = i.CD_ALMOXARIFADO
LEFT JOIN dbo.TBL_MATERIAIS_ESTOQUE est ON est.CD_MATERIAL  = i.CD_MATERIAL
                                       AND est.CD_FILIAL    = s.CD_FILIAL
WHERE   i.CD_SOLICITACAO = @CD_SOLICITACAO
ORDER BY i.CD_ITEM;


/* ============================================================================
   3. ÚLTIMA COMPRA DE CADA ITEM DA SOLICITAÇÃO
      (fornecedor + valor unitário + data, em uma única ida ao banco)
      Fonte: NF de entrada (compra efetivada). Ver bloco 8 para OC.
   ========================================================================== */
DECLARE @SOMENTE_MESMA_FILIAL bit = 0;   -- 1 = histórico só da filial da SC
DECLARE @CD_STATUS_CANCELADO  int = NULL; -- preencher com o resultado da query 0.1

SELECT  i.CD_ITEM,
        i.CD_MATERIAL,
        DS_MATERIAL = COALESCE(NULLIF(i.DS_MATERIAL, ''), mat.DS_MATERIAL),
        i.NR_QUANTIDADE           AS QTD_SOLICITADA,
        uc.DT_ENTRADA             AS ULT_COMPRA_DATA,
        uc.DT_EMISSAO             AS ULT_COMPRA_EMISSAO,
        uc.NR_DOCUMENTO           AS ULT_COMPRA_NF,
        uc.CD_ENTRADA             AS ULT_COMPRA_LANCAMENTO,
        uc.CD_FORNECEDOR          AS ULT_COMPRA_CD_FORNECEDOR,
        uc.DS_ENTIDADE            AS ULT_COMPRA_FORNECEDOR,
        uc.DS_FANTASIA            AS ULT_COMPRA_FORNECEDOR_FANTASIA,
        uc.NR_CPFCNPJ             AS ULT_COMPRA_CNPJ,
        uc.NR_QUANTIDADE          AS ULT_COMPRA_QTD,
        uc.DS_UNIDADE             AS ULT_COMPRA_UN,
        uc.VL_UNITARIO            AS ULT_COMPRA_VL_UNITARIO,   -- preço de nota
        uc.VL_CUSTO_COMPRA        AS ULT_COMPRA_VL_CUSTO,      -- custo apurado pelo Questor
        uc.VL_TOTAL               AS ULT_COMPRA_VL_TOTAL,
        uc.DS_CME                 AS ULT_COMPRA_OPERACAO,
        uc.CD_FILIAL              AS ULT_COMPRA_FILIAL,
        -- variação da última compra contra o custo médio atual
        VARIACAO_VS_CUSTO_MEDIO = CASE WHEN ISNULL(est.VL_CUSTO_MEDIO,0) > 0
                                       THEN (uc.VL_UNITARIO - est.VL_CUSTO_MEDIO) / est.VL_CUSTO_MEDIO
                                  END
FROM    dbo.TBL_COMPRAS_SOLICITACAO_ITENS i
JOIN    dbo.TBL_COMPRAS_SOLICITACAO s ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
LEFT JOIN dbo.TBL_MATERIAIS mat       ON mat.CD_MATERIAL  = i.CD_MATERIAL
LEFT JOIN dbo.TBL_MATERIAIS_ESTOQUE est ON est.CD_MATERIAL = i.CD_MATERIAL
                                       AND est.CD_FILIAL   = s.CD_FILIAL
OUTER APPLY (
    SELECT TOP (1)
           e.CD_ENTRADA, e.DT_ENTRADA, e.DT_EMISSAO, e.NR_DOCUMENTO,
           e.CD_FORNECEDOR, e.CD_FILIAL,
           ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
           ni.NR_QUANTIDADE, ni.DS_UNIDADE, ni.VL_UNITARIO,
           ni.VL_CUSTO_COMPRA, ni.VL_TOTAL, ni.DS_CME
    FROM   dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni
    JOIN   dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
    JOIN   dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = e.CD_FORNECEDOR
    LEFT JOIN dbo.TBL_CME cme ON cme.CD_CME = ni.CD_CME
    WHERE  ni.CD_MATERIAL = i.CD_MATERIAL
      AND  e.CD_EMPRESA   = s.CD_EMPRESA
      AND  (@SOMENTE_MESMA_FILIAL = 0 OR e.CD_FILIAL = s.CD_FILIAL)
      AND  (@CD_STATUS_CANCELADO IS NULL OR e.CD_STATUS <> @CD_STATUS_CANCELADO)
      AND  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1   -- exclui devolução/remessa/transferência
      AND  ni.VL_UNITARIO > 0
    ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC, ni.CD_ITEM DESC
) uc
WHERE   i.CD_SOLICITACAO = @CD_SOLICITACAO
ORDER BY i.CD_ITEM;


/* ============================================================================
   4. HISTÓRICO DE COMPRAS DE UM MATERIAL (drill-down do item)
      N últimas entradas, com fornecedor, quantidade, preço e nota.
   ========================================================================== */
DECLARE @CD_MATERIAL int = NULL,
        @TOP_HIST    int = 20,
        @MESES_HIST  int = 24;

SELECT TOP (@TOP_HIST)
        e.CD_ENTRADA,
        e.DT_ENTRADA,
        e.DT_EMISSAO,
        e.NR_DOCUMENTO,
        e.DS_SERIE,
        e.CD_FILIAL,
        fil.DS_FILIAL,
        e.CD_FORNECEDOR,
        ent.DS_ENTIDADE,
        ent.DS_FANTASIA,
        ent.NR_CPFCNPJ,
        cid.DS_CIDADE,
        cid.DS_UF,
        ni.CD_ITEM,
        ni.DS_MATERIAL,
        ni.DS_UNIDADE,
        ni.NR_QUANTIDADE,
        ni.VL_UNITARIO,
        ni.VL_DESCONTO,
        ni.VL_IPI,
        ni.VL_ICMS_SUBST,
        ni.VL_FRETENF,
        ni.VL_TOTAL,
        ni.VL_CUSTO_COMPRA,
        ni.CD_CME,
        ni.DS_CME,
        fpg.DS_FORMA_PAGAMENTO,
        frt.DS_FRETE,
        e.CD_ORDEM_COMPRA,
        e.CD_STATUS,
        stn.DS_STATUS
FROM    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni
JOIN    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = e.CD_FORNECEDOR
LEFT JOIN dbo.TBL_ENDERECO_CIDADES cid ON cid.CD_CIDADE = ent.CD_CIDADE
LEFT JOIN dbo.TBL_EMPRESAS_FILIAIS fil ON fil.CD_FILIAL = e.CD_FILIAL
LEFT JOIN dbo.TBL_FINANCEIRO_FORMAS_PAGAMENTO fpg ON fpg.CD_FORMA_PAGAMENTO = e.CD_FORMA_PAGAMENTO
LEFT JOIN dbo.TBL_FRETES frt ON frt.CD_FRETE = e.CD_FRETE
LEFT JOIN dbo.TBL_CME cme    ON cme.CD_CME   = ni.CD_CME
LEFT JOIN dbo.TBL_STATUS stn ON stn.CD_STATUS = e.CD_STATUS     -- sem FK: LEFT obrigatório
WHERE   ni.CD_MATERIAL = @CD_MATERIAL
  AND   e.DT_ENTRADA >= DATEADD(month, -@MESES_HIST, GETDATE())
  AND   (@CD_STATUS_CANCELADO IS NULL OR e.CD_STATUS <> @CD_STATUS_CANCELADO)
  AND   ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC;


/* ============================================================================
   5. TODOS OS FORNECEDORES QUE JÁ FORNECERAM O MATERIAL (agregado)
      Base para sugerir a quem pedir cotação e para as colunas do mapa.
   ========================================================================== */
SELECT  e.CD_FORNECEDOR,
        ent.DS_ENTIDADE,
        ent.DS_FANTASIA,
        ent.NR_CPFCNPJ,
        ent.NR_TELEFONE,
        ent.DS_EMAIL,
        ent.DS_EMAIL_ORD_COMPRA,
        ent.X_ATIVO,
        ent.X_FORNECEDOR,
        cid.DS_CIDADE,
        cid.DS_UF,
        QTD_COMPRAS      = COUNT(DISTINCT e.CD_ENTRADA),
        QTD_TOTAL        = SUM(ni.NR_QUANTIDADE),
        VL_TOTAL_COMPRADO= SUM(ni.VL_TOTAL),
        DT_PRIMEIRA      = MIN(e.DT_ENTRADA),
        DT_ULTIMA        = MAX(e.DT_ENTRADA),
        VL_UNIT_MIN      = MIN(ni.VL_UNITARIO),
        VL_UNIT_MAX      = MAX(ni.VL_UNITARIO),
        VL_UNIT_MEDIO    = AVG(ni.VL_UNITARIO),
        -- preço da compra mais recente deste fornecedor
        VL_UNIT_ULTIMO   = MAX(CASE WHEN rn.RN = 1 THEN ni.VL_UNITARIO END),
        DS_UNIDADE_ULT   = MAX(CASE WHEN rn.RN = 1 THEN ni.DS_UNIDADE END)
FROM    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni
JOIN    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = e.CD_FORNECEDOR
LEFT JOIN dbo.TBL_ENDERECO_CIDADES cid ON cid.CD_CIDADE = ent.CD_CIDADE
LEFT JOIN dbo.TBL_CME cme ON cme.CD_CME = ni.CD_CME
CROSS APPLY (
    SELECT RN = ROW_NUMBER() OVER (PARTITION BY e.CD_FORNECEDOR
                                   ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC)
) rn
WHERE   ni.CD_MATERIAL = @CD_MATERIAL
  AND   (@CD_STATUS_CANCELADO IS NULL OR e.CD_STATUS <> @CD_STATUS_CANCELADO)
  AND   ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
  AND   ni.VL_UNITARIO > 0
GROUP BY e.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
         ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA,
         ent.X_ATIVO, ent.X_FORNECEDOR, cid.DS_CIDADE, cid.DS_UF
ORDER BY DT_ULTIMA DESC;

/* Nota sobre a janela ROW_NUMBER acima: o CROSS APPLY calcula o ranking por
   fornecedor dentro do conjunto filtrado. Se preferir uma forma mais explícita,
   use a versão em CTE da query 5.b. */

-- 5.b Versão em CTE (mesma saída, plano mais previsível em volumes grandes)
;WITH COMPRAS AS (
    SELECT  e.CD_ENTRADA, e.DT_ENTRADA, e.CD_FORNECEDOR,
            ni.NR_QUANTIDADE, ni.VL_UNITARIO, ni.VL_TOTAL, ni.DS_UNIDADE,
            RN = ROW_NUMBER() OVER (PARTITION BY e.CD_FORNECEDOR
                                    ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC)
    FROM    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni
    JOIN    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
    LEFT JOIN dbo.TBL_CME cme ON cme.CD_CME = ni.CD_CME
    WHERE   ni.CD_MATERIAL = @CD_MATERIAL
      AND   (@CD_STATUS_CANCELADO IS NULL OR e.CD_STATUS <> @CD_STATUS_CANCELADO)
      AND   ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
      AND   ni.VL_UNITARIO > 0
)
SELECT  c.CD_FORNECEDOR,
        ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
        ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO,
        QTD_COMPRAS    = COUNT(*),
        DT_ULTIMA      = MAX(c.DT_ENTRADA),
        VL_UNIT_MEDIO  = AVG(c.VL_UNITARIO),
        VL_UNIT_MIN    = MIN(c.VL_UNITARIO),
        VL_UNIT_ULTIMO = MAX(CASE WHEN c.RN = 1 THEN c.VL_UNITARIO END)
FROM    COMPRAS c
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
GROUP BY c.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
         ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO
ORDER BY DT_ULTIMA DESC;


/* ============================================================================
   5.c FORNECEDORES DE TODOS OS ITENS DA SC DE UMA VEZ
       (uma query só, para montar a lista de colunas candidatas do mapa)
   ========================================================================== */
;WITH ITENS AS (
    SELECT i.CD_ITEM, i.CD_MATERIAL, s.CD_EMPRESA, s.CD_FILIAL
    FROM   dbo.TBL_COMPRAS_SOLICITACAO_ITENS i
    JOIN   dbo.TBL_COMPRAS_SOLICITACAO s ON s.CD_SOLICITACAO = i.CD_SOLICITACAO
    WHERE  i.CD_SOLICITACAO = @CD_SOLICITACAO
      AND  i.CD_MATERIAL IS NOT NULL
), COMPRAS AS (
    SELECT  it.CD_ITEM, it.CD_MATERIAL, e.CD_FORNECEDOR,
            e.DT_ENTRADA, ni.VL_UNITARIO, ni.DS_UNIDADE, ni.NR_QUANTIDADE,
            RN = ROW_NUMBER() OVER (PARTITION BY it.CD_ITEM, e.CD_FORNECEDOR
                                    ORDER BY e.DT_ENTRADA DESC, e.CD_ENTRADA DESC)
    FROM    ITENS it
    JOIN    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni ON ni.CD_MATERIAL = it.CD_MATERIAL
    JOIN    dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
                                                AND e.CD_EMPRESA = it.CD_EMPRESA
    LEFT JOIN dbo.TBL_CME cme ON cme.CD_CME = ni.CD_CME
    WHERE   (@CD_STATUS_CANCELADO IS NULL OR e.CD_STATUS <> @CD_STATUS_CANCELADO)
      AND   ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1
      AND   ni.VL_UNITARIO > 0
)
SELECT  c.CD_ITEM,
        c.CD_MATERIAL,
        c.CD_FORNECEDOR,
        ent.DS_ENTIDADE,
        ent.DS_FANTASIA,
        ent.NR_TELEFONE,
        ent.DS_EMAIL,
        ent.DS_EMAIL_ORD_COMPRA,
        ent.X_ATIVO,
        QTD_COMPRAS    = COUNT(*),
        DT_ULTIMA      = MAX(c.DT_ENTRADA),
        VL_UNIT_ULTIMO = MAX(CASE WHEN c.RN = 1 THEN c.VL_UNITARIO END),
        VL_UNIT_MEDIO  = AVG(c.VL_UNITARIO)
FROM    COMPRAS c
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
GROUP BY c.CD_ITEM, c.CD_MATERIAL, c.CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA,
         ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA, ent.X_ATIVO
ORDER BY c.CD_ITEM, DT_ULTIMA DESC;


/* ============================================================================
   6. FORNECEDORES HOMOLOGADOS NO CADASTRO DO MATERIAL
      (TBL_MATERIAIS_FORNECEDOR — quem PODE fornecer, mesmo sem histórico)
   ========================================================================== */
SELECT  mf.CD_MATERIAL,
        mf.CD_FORNECEDOR,
        ent.DS_ENTIDADE,
        ent.DS_FANTASIA,
        ent.NR_CPFCNPJ,
        ent.NR_TELEFONE,
        ent.DS_EMAIL,
        ent.DS_EMAIL_ORD_COMPRA,
        ent.X_ATIVO,
        mf.CD_MATERIAL_FORNECEDOR       AS COD_PRODUTO_NO_FORNECEDOR,
        mf.DS_NOME_PRODUTO_FORNECEDOR,
        mf.CD_UNIDADE_CONVERSAO,
        mf.DT_CADASTRO
FROM    dbo.TBL_MATERIAIS_FORNECEDOR mf
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = mf.CD_FORNECEDOR
WHERE   mf.CD_MATERIAL = @CD_MATERIAL
ORDER BY ent.DS_ENTIDADE;


/* ============================================================================
   7. COTAÇÕES ANTERIORES REGISTRADAS NO PRÓPRIO QUESTOR
      Útil para pré-preencher preços e para ver quem já respondeu cotação antes.
   ========================================================================== */
-- 7.a Cotações ligadas a esta solicitação
SELECT  c.CD_COTACAO, c.DT_EMISSAO, c.CD_FORNECEDOR,
        ent.DS_ENTIDADE, ent.DS_FANTASIA,
        c.DS_COMPRADOR, c.DS_REQUISITANTE, c.DS_REFERENTE,
        c.VL_FRETE, c.VL_TOTAL,
        frt.DS_FRETE, fpg.DS_FORMA_PAGAMENTO, pze.DS_PRAZO_ENTREGA,
        c.CD_STATUS, st.DS_STATUS, c.DT_AUTORIZACAO, c.DS_MOTIVO_REPROVADO
FROM    dbo.TBL_COMPRAS_COTACAO c
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
LEFT JOIN dbo.TBL_FRETES frt ON frt.CD_FRETE = c.CD_FRETE
LEFT JOIN dbo.TBL_FINANCEIRO_FORMAS_PAGAMENTO fpg ON fpg.CD_FORMA_PAGAMENTO = c.CD_FORMA_PAGAMENTO
LEFT JOIN dbo.TBL_PRAZO_ENTREGA pze ON pze.CD_PRAZO_ENTREGA = c.CD_PRAZO_ENTREGA
LEFT JOIN dbo.TBL_STATUS st ON st.CD_STATUS = c.CD_STATUS
WHERE   c.CD_SOLICITACAO = @CD_SOLICITACAO
ORDER BY c.DT_EMISSAO DESC, ent.DS_ENTIDADE;

-- 7.b Últimas cotações de um material (independente da SC)
SELECT TOP (30)
        c.CD_COTACAO, c.DT_EMISSAO, c.CD_FORNECEDOR,
        ent.DS_ENTIDADE, ci.DS_MATERIAL, ci.DS_UNIDADE,
        ci.NR_QUANTIDADE, ci.VL_UNITARIO, ci.VL_TOTAL, ci.VL_FRETE, ci.DS_OBS
FROM    dbo.TBL_COMPRAS_COTACAO_ITENS ci
JOIN    dbo.TBL_COMPRAS_COTACAO c ON c.CD_COTACAO = ci.CD_COTACAO
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = c.CD_FORNECEDOR
WHERE   ci.CD_MATERIAL = @CD_MATERIAL
ORDER BY c.DT_EMISSAO DESC;


/* ============================================================================
   8. ORDENS DE COMPRA DO MATERIAL (compra pedida, ainda não faturada)
      Complementa a "última compra" quando a NF ainda não entrou.
   ========================================================================== */
SELECT TOP (20)
        oc.CD_ORDEM_COMPRA, oc.DT_EMISSAO, oc.DT_ENTREGA_PREVISTA,
        oc.CD_ENTIDADE AS CD_FORNECEDOR, ent.DS_ENTIDADE, ent.DS_FANTASIA,
        oci.DS_MATERIAL, oci.DS_UNIDADE, oci.NR_QUANTIDADE, oci.NR_SALDO,
        oci.VL_UNITARIO, oci.VL_TOTAL, oci.DT_ENTREGA,
        oc.CD_STATUS, st.DS_STATUS, oc.DT_AUTORIZACAO,
        frt.DS_FRETE, pze.DS_PRAZO_ENTREGA, fpg.DS_FORMA_PAGAMENTO
FROM    dbo.TBL_COMPRAS_ORDEM_COMPRA_ITENS oci
JOIN    dbo.TBL_COMPRAS_ORDEM_COMPRA oc ON oc.CD_ORDEM_COMPRA = oci.CD_ORDEM_COMPRA
JOIN    dbo.TBL_ENTIDADES ent ON ent.CD_ENTIDADE = oc.CD_ENTIDADE
LEFT JOIN dbo.TBL_STATUS st ON st.CD_STATUS = oc.CD_STATUS
LEFT JOIN dbo.TBL_FRETES frt ON frt.CD_FRETE = oc.CD_FRETE
LEFT JOIN dbo.TBL_PRAZO_ENTREGA pze ON pze.CD_PRAZO_ENTREGA = oc.CD_PRAZO_ENTREGA
LEFT JOIN dbo.TBL_FINANCEIRO_FORMAS_PAGAMENTO fpg ON fpg.CD_FORMA_PAGAMENTO = oc.CD_FORMA_PAGAMENTO
WHERE   oci.CD_MATERIAL = @CD_MATERIAL
ORDER BY oc.DT_EMISSAO DESC;


/* ============================================================================
   9. TABELAS AUXILIARES (combos do mapa)
   ========================================================================== */
-- 9.a Modalidades de frete (CIF/FOB/...)
SELECT CD_FRETE, DS_FRETE, NR_MODALIDADE_NFE FROM dbo.TBL_FRETES ORDER BY CD_FRETE;

-- 9.b Prazos de entrega cadastrados
SELECT CD_PRAZO_ENTREGA, DS_PRAZO_ENTREGA, NR_DIAS FROM dbo.TBL_PRAZO_ENTREGA ORDER BY NR_DIAS;

-- 9.c Formas de pagamento ativas
SELECT CD_FORMA_PAGAMENTO, DS_FORMA_PAGAMENTO, DS_ABREVIACAO
FROM   dbo.TBL_FINANCEIRO_FORMAS_PAGAMENTO
WHERE  X_ATIVO = 1 AND X_SOMENTE_COMPRA = 1
ORDER  BY DS_FORMA_PAGAMENTO;

-- 9.d Autocomplete de fornecedor (para adicionar coluna manual no mapa)
DECLARE @BUSCA_FORN varchar(100) = 'TINTAS';
SELECT TOP (30)
       ent.CD_ENTIDADE, ent.DS_ENTIDADE, ent.DS_FANTASIA, ent.NR_CPFCNPJ,
       ent.NR_TELEFONE, ent.DS_EMAIL, ent.DS_EMAIL_ORD_COMPRA,
       cid.DS_CIDADE, cid.DS_UF
FROM   dbo.TBL_ENTIDADES ent
LEFT JOIN dbo.TBL_ENDERECO_CIDADES cid ON cid.CD_CIDADE = ent.CD_CIDADE
WHERE  ent.X_FORNECEDOR = 1
  AND  ent.X_ATIVO = 1
  AND ( ent.DS_ENTIDADE LIKE '%' + @BUSCA_FORN + '%'
     OR ent.DS_FANTASIA LIKE '%' + @BUSCA_FORN + '%'
     OR ent.NR_CPFCNPJ_SEM_FORMATO LIKE @BUSCA_FORN + '%' )
ORDER BY ent.DS_ENTIDADE;

-- 9.e Autocomplete de material (item avulso adicionado ao mapa)
DECLARE @BUSCA_MAT varchar(100) = 'TINTA ESMALTE';
SELECT TOP (30)
       m.CD_MATERIAL, m.DS_MATERIAL, m.CD_REFERENCIA, m.CD_NCM,
       un.DS_ABREVIATURA AS DS_UNIDADE, m.X_ATIVO
FROM   dbo.TBL_MATERIAIS m
LEFT JOIN dbo.TBL_MATERIAIS_UNIDADE un ON un.CD_UNIDADE = m.CD_UNIDADE
WHERE  m.X_ATIVO = 1
  AND (m.DS_MATERIAL LIKE '%' + @BUSCA_MAT + '%' OR m.CD_REFERENCIA = @BUSCA_MAT)
ORDER BY m.DS_MATERIAL;


/* ============================================================================
   10. VIEWS SUGERIDAS (criar em schema próprio, ex.: cons — NUNCA em dbo)
   ========================================================================== */
/*
CREATE VIEW cons.VW_MAPA_SOLICITACAO_ITENS AS
SELECT ... (query 2 sem o WHERE);

CREATE VIEW cons.VW_COMPRAS_MATERIAL AS
SELECT ni.CD_MATERIAL, e.CD_EMPRESA, e.CD_FILIAL, e.CD_ENTRADA, e.DT_ENTRADA,
       e.CD_FORNECEDOR, ni.NR_QUANTIDADE, ni.DS_UNIDADE, ni.VL_UNITARIO,
       ni.VL_CUSTO_COMPRA, ni.VL_TOTAL, ni.CD_CME, e.CD_STATUS
FROM   dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS ni
JOIN   dbo.TBL_COMPRAS_NOTAFISCAL_ENTRADA e ON e.CD_ENTRADA = ni.CD_ENTRADA
LEFT JOIN dbo.TBL_CME cme ON cme.CD_CME = ni.CD_CME
WHERE  ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1;
*/


/* ============================================================================
   11. ÍNDICES RECOMENDADOS (se houver permissão no Questor — validar com o
       fornecedor do ERP antes; índice novo em base de ERP é decisão do DBA)
   ----------------------------------------------------------------------------
   Já existem e cobrem bem o caso:
     IDQ_TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS_CD_MATERIAL (CD_MATERIAL, CD_ENTRADA)
     IDX_TBL_COMPRAS_NOTAFISCAL_ENTRADA_ITENS_CD_MATERIAL (CD_MATERIAL)
   O gargalo tende a ser o ORDER BY e.DT_ENTRADA DESC após o join. Se ficar lento,
   avaliar índice em TBL_COMPRAS_NOTAFISCAL_ENTRADA (CD_ENTRADA) INCLUDE
   (DT_ENTRADA, CD_FORNECEDOR, CD_STATUS, CD_FILIAL, CD_EMPRESA).
   ========================================================================== */

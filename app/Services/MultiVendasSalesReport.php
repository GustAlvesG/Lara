<?php

namespace App\Services;

use App\Exceptions\SalesReportException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de fechamento do MultiVendas — o "cupom" de um vendedor num
 * período, que apura quanto ele vendeu e que serve de base para a comissão do
 * garçom.
 *
 * A consulta é a que a operação escreveu e conferiu, preservada quase à letra:
 * os `DECLARE` continuam no lugar (agora alimentados por bindings, não por
 * literais) e as seções são as mesmas. As duas únicas mudanças no SQL são
 * `Secao` e `Ordem` no SELECT final — sem eles, achar "o total" no resultado
 * dependeria de comparar textos acentuados, e um acento a mais no relatório
 * mudaria silenciosamente a base da comissão.
 */
class MultiVendasSalesReport
{
    public const CONNECTION = 'mv_sqlsrv';

    /** Seções do cupom, na ordem em que saem. */
    public const SECTIONS = [
        1 => 'CABEÇALHO',
        2 => 'ITENS',
        3 => 'RECEBIMENTOS',
        4 => 'TOTAIS',
        5 => 'CANCELAMENTOS',
    ];

    /**
     * Linhas da seção TOTAIS, por `Ordem`. É a chave estável do resultado: a
     * base da comissão é lida daqui, não do texto da linha.
     */
    public const TOTALS = [
        1 => 'sales_count',
        2 => 'gross',
        3 => 'discounts',
        4 => 'net_items',
        5 => 'sales_total',
        6 => 'received',
        7 => 'difference',
        8 => 'average_ticket',
    ];

    /**
     * Qual total vira a base da comissão: `Sales.Total`, o total de cabeçalho
     * das vendas — é ele que a operação chama de "valor total da venda". Para
     * comissionar sobre o líquido dos itens, troque para 'net_items'.
     */
    public const COMMISSION_BASE = 'sales_total';

    /**
     * Apura o período de um vendedor.
     *
     * @return array{login: string, period: array{start: string, end: string},
     *               sections: array<string, array<int, array<string, mixed>>>,
     *               totals: array<string, float|null>, base: float,
     *               generated_at: string}
     * @throws SalesReportException quando o MultiVendas não responde
     */
    public function forSeller(string $login, Carbon $start, Carbon $end): array
    {
        $login = trim($login);

        if ($login === '') {
            throw new SalesReportException('Informe o login do vendedor no MultiVendas.');
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw new SalesReportException('O fim do período deve ser posterior ao início.');
        }

        try {
            $rows = DB::connection(self::CONNECTION)->select($this->sql(), [
                $login,
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            throw new SalesReportException(
                'Não foi possível consultar as vendas no MultiVendas. Tente novamente ou informe o valor manualmente.'
            );
        }

        return $this->shape($rows, $login, $start, $end);
    }

    /**
     * Organiza as linhas cruas em seções e totais.
     *
     * @param  array<int, object>  $rows
     */
    private function shape(array $rows, string $login, Carbon $start, Carbon $end): array
    {
        $sections = [];
        $totals = array_fill_keys(array_values(self::TOTALS), null);

        foreach ($rows as $row) {
            $section = self::SECTIONS[(int) $row->Secao] ?? (string) $row->Secao;

            $sections[$section][] = [
                'descricao' => (string) $row->Descricao,
                'qtde' => $row->Qtde === null ? null : (float) $row->Qtde,
                'un' => $row->Un,
                'valor_unit' => $row->ValorUnit === null ? null : (float) $row->ValorUnit,
                'valor' => $row->Valor === null ? null : (float) $row->Valor,
            ];

            if ((int) $row->Secao === 4 && isset(self::TOTALS[(int) $row->Ordem])) {
                $key = self::TOTALS[(int) $row->Ordem];
                // "Qtd. de vendas" vem em Qtde; os demais totais, em Valor.
                $value = $key === 'sales_count' ? $row->Qtde : $row->Valor;
                $totals[$key] = $value === null ? null : (float) $value;
            }
        }

        return [
            'login' => $login,
            'period' => [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ],
            'sections' => $sections,
            'totals' => $totals,
            'base' => (float) ($totals[self::COMMISSION_BASE] ?? 0),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * O cupom de fechamento. Os `DECLARE` recebem os três parâmetros por
     * binding; o resto é a consulta da operação, sem alteração de lógica.
     */
    private function sql(): string
    {
        return <<<'SQL'
DECLARE @Login       NVARCHAR(50) = ?;
DECLARE @DataInicial DATETIME     = ?;
DECLARE @DataFinal   DATETIME     = ?;

WITH
/* --- vendas válidas do vendedor no período ------------------------------ */
Vendas AS (
    SELECT
        S.SaleNumber,
        S.SaleUid,
        S.CashNumber,
        S.ClosingDateTime,
        S.Total,
        U.Name AS VendedorNome
    FROM dbo.Sales AS S
    JOIN dbo.Users AS U ON U.Id = S.[User]
    WHERE U.UserName         = @Login
      AND S.ClosingDateTime >= @DataInicial
      AND S.ClosingDateTime <= @DataFinal
      AND S.CanceledManager IS NULL
      AND S.Cancellation    IS NULL
      AND S.SaleType NOT IN (4, 7)
),
VendasCanceladas AS (
    SELECT S.SaleNumber, S.Total
    FROM dbo.Sales AS S
    JOIN dbo.Users AS U ON U.Id = S.[User]
    WHERE U.UserName         = @Login
      AND S.ClosingDateTime >= @DataInicial
      AND S.ClosingDateTime <= @DataFinal
      AND (S.CanceledManager IS NOT NULL OR S.Cancellation IS NOT NULL)
      AND S.SaleType NOT IN (4, 7)
),
/* --- itens válidos, com acréscimo/desconto rateado ---------------------- */
Itens AS (
    SELECT
        SI.SaleNumber,
        P.MainBarcode,
        P.Name     AS Produto,
        MU.Acronym AS Un,
        SI.Quantity,
        SI.SubTotal + IC.[Value]                                   AS Bruto,
        COALESCE(SI.RatedDiscount, 0) + IC.RatedDiscount           AS Desconto,
        SI.SubTotal + IC.[Value]
          - (COALESCE(SI.RatedDiscount, 0) + IC.RatedDiscount)     AS Liquido
    FROM dbo.SaleItems    AS SI
    JOIN Vendas           AS V   ON V.SaleNumber = SI.SaleNumber
    JOIN dbo.Products     AS P   ON P.Id  = SI.Product
    JOIN dbo.MeasureUnits AS MU  ON MU.ID = P.MeasureUnit
    OUTER APPLY (
        SELECT COALESCE(SUM(SIC.[Value]), 0)       AS [Value],
               COALESCE(SUM(SIC.RatedDiscount), 0) AS RatedDiscount
        FROM dbo.SaleItemIncreaseCharges AS SIC
        WHERE SIC.SaleNumber = SI.SaleNumber
          AND SIC.ItemNumber = SI.ItemNumber
    ) AS IC
    WHERE SI.CancelManager IS NULL
      AND SI.Cancellation  IS NULL
),
/* --- RECEBIMENTOS: as duas origens em uma lista só ---------------------- */
Recebimentos AS (
    /* 1) formas de pagamento do PDV: dinheiro, cartão, TEF, Pix, voucher... */
    SELECT
        N'PDV'                              AS Origem,
        PM.Name                             AS FormaPagamento,
        SP.ReceivedValue - SP.ChangeValue   AS Valor
    FROM dbo.SalePayments AS SP
    JOIN Vendas           AS V  ON V.SaleNumber = SP.SaleNumber
    JOIN dbo.Paymodes     AS PM ON PM.Id        = SP.PayMode

    UNION ALL

    /* 2) consumo lançado em conta / cartão de consumo                      */
    SELECT
        N'Conta/Cartão de consumo',
        COALESCE(PM.Name, N'Conta'),
        AM.[Value]
    FROM dbo.AccountMovements AS AM
    JOIN Vendas               AS V  ON V.SaleUid = AM.OperationUid
    LEFT JOIN dbo.Paymodes    AS PM ON PM.Id     = AM.PayMode
    WHERE AM.Origin       = 0            -- consumo (mesma regra da PromoterConsumptions)
      AND AM.MovementType IN (0, 2)
      AND AM.IsReversed   = 0
      AND AM.Cancellation IS NULL
      AND (AM.TransferMovementType IS NULL OR AM.TransferMovementType <> -1)
),
/* --- corpo do cupom ---------------------------------------------------- */
Cupom AS (
    /* ---------- CABEÇALHO ---------- */
    SELECT
        1                                              AS Secao,
        N'CABEÇALHO'                                   AS SecaoNome,
        1                                              AS Ordem,
        CAST(MAX(V.VendedorNome) + N'  (' + @Login + N')' AS NVARCHAR(200)) AS Descricao,
        CAST(NULL AS DECIMAL(18,3))                    AS Qtde,
        CAST(NULL AS NVARCHAR(10))                     AS Un,
        CAST(NULL AS DECIMAL(18,2))                    AS ValorUnit,
        CAST(NULL AS DECIMAL(18,2))                    AS Valor
    FROM Vendas AS V

    UNION ALL
    SELECT 1, N'CABEÇALHO', 2,
        CAST(N'Período: '
             + CONVERT(NVARCHAR(19), @DataInicial, 120) + N' a '
             + CONVERT(NVARCHAR(19), @DataFinal,   120) AS NVARCHAR(200)),
        NULL, NULL, NULL, NULL

    UNION ALL
    SELECT 1, N'CABEÇALHO', 3,
        CAST(N'Lojas: ' + STUFF((
                SELECT DISTINCT N', ' + Sh.Name
                FROM Vendas AS V2
                JOIN dbo.Cashes AS C  ON C.CashNumber = V2.CashNumber
                JOIN dbo.Shops  AS Sh ON Sh.Id        = C.Shop
                FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '')
             AS NVARCHAR(200)),
        NULL, NULL, NULL, NULL

    /* ---------- ITENS (consolidado por produto) ---------- */
    UNION ALL
    SELECT
        2, N'ITENS',
        ROW_NUMBER() OVER (ORDER BY SUM(I.Liquido) DESC),
        CAST(COALESCE(I.MainBarcode + N' - ', N'') + I.Produto AS NVARCHAR(200)),
        CAST(SUM(I.Quantity) AS DECIMAL(18,3)),
        CAST(I.Un AS NVARCHAR(10)),
        CAST(CASE WHEN SUM(I.Quantity) = 0 THEN NULL
                  ELSE SUM(I.Liquido) / SUM(I.Quantity) END AS DECIMAL(18,2)),
        CAST(SUM(I.Liquido) AS DECIMAL(18,2))
    FROM Itens AS I
    GROUP BY I.MainBarcode, I.Produto, I.Un

    /* ---------- RECEBIMENTOS (detalhado por forma) ---------- */
    UNION ALL
    SELECT
        3, N'RECEBIMENTOS',
        ROW_NUMBER() OVER (ORDER BY R.Origem, SUM(R.Valor) DESC),
        CAST(R.Origem + N' :: ' + R.FormaPagamento AS NVARCHAR(200)),
        CAST(COUNT(*) AS DECIMAL(18,3)),
        CAST(N'x' AS NVARCHAR(10)),
        NULL,
        CAST(SUM(R.Valor) AS DECIMAL(18,2))
    FROM Recebimentos AS R
    GROUP BY R.Origem, R.FormaPagamento

    /* ---------- RECEBIMENTOS (subtotal por origem) ---------- */
    UNION ALL
    SELECT
        3, N'RECEBIMENTOS', 900 + ROW_NUMBER() OVER (ORDER BY R.Origem),
        CAST(N'>> Subtotal ' + R.Origem AS NVARCHAR(200)),
        NULL, NULL, NULL,
        CAST(SUM(R.Valor) AS DECIMAL(18,2))
    FROM Recebimentos AS R
    GROUP BY R.Origem

    /* ---------- TOTAIS ---------- */
    UNION ALL
    SELECT 4, N'TOTAIS', 1, N'Qtd. de vendas',
        CAST(COUNT(*) AS DECIMAL(18,3)), NULL, NULL, NULL
    FROM Vendas

    UNION ALL
    SELECT 4, N'TOTAIS', 2, N'Total bruto (itens)',
        NULL, NULL, NULL, CAST(SUM(I.Bruto) AS DECIMAL(18,2))
    FROM Itens AS I

    UNION ALL
    SELECT 4, N'TOTAIS', 3, N'(-) Descontos',
        NULL, NULL, NULL, CAST(SUM(I.Desconto) AS DECIMAL(18,2))
    FROM Itens AS I

    UNION ALL
    SELECT 4, N'TOTAIS', 4, N'= Total líquido (itens)',
        NULL, NULL, NULL, CAST(SUM(I.Liquido) AS DECIMAL(18,2))
    FROM Itens AS I

    UNION ALL
    SELECT 4, N'TOTAIS', 5, N'Total cabeçalho (Sales.Total)',
        NULL, NULL, NULL, CAST(SUM(V.Total) AS DECIMAL(18,2))
    FROM Vendas AS V

    UNION ALL
    SELECT 4, N'TOTAIS', 6, N'Total recebido (todas as origens)',
        NULL, NULL, NULL, CAST(SUM(R.Valor) AS DECIMAL(18,2))
    FROM Recebimentos AS R

    UNION ALL
    SELECT 4, N'TOTAIS', 7, N'Diferença (recebido - cabeçalho)',
        NULL, NULL, NULL,
        CAST((SELECT COALESCE(SUM(R.Valor), 0) FROM Recebimentos AS R)
           - (SELECT COALESCE(SUM(V.Total), 0) FROM Vendas AS V) AS DECIMAL(18,2))

    UNION ALL
    SELECT 4, N'TOTAIS', 8, N'Ticket médio',
        NULL, NULL, NULL,
        CAST(CASE WHEN COUNT(*) = 0 THEN 0 ELSE SUM(V.Total) / COUNT(*) END
             AS DECIMAL(18,2))
    FROM Vendas AS V

    /* ---------- CANCELAMENTOS ---------- */
    UNION ALL
    SELECT 5, N'CANCELAMENTOS', 1, N'Vendas canceladas no período',
        CAST(COUNT(*) AS DECIMAL(18,3)), NULL, NULL,
        CAST(COALESCE(SUM(VC.Total), 0) AS DECIMAL(18,2))
    FROM VendasCanceladas AS VC
)
SELECT
    Secao,
    Ordem,
    SecaoNome AS Secao_Nome,
    Descricao,
    Qtde,
    Un,
    ValorUnit,
    Valor
FROM Cupom
ORDER BY Secao, Ordem;
SQL;
    }
}

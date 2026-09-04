<?php

namespace App\Services\Questor\DTO;

use Illuminate\Support\Carbon;

/**
 * A última compra efetivada de um item (query 3) — fornecedor, preço, data e NF.
 *
 * "Compra" aqui é o que o próprio Questor chama de compra: a nota de entrada
 * cuja operação tem `TBL_CME.X_ATUALIZA_DT_ULTIMA_COMPRA = 1`. É o flag que o
 * ERP usa, e filtrar por ele exclui devolução, transferência, remessa e
 * industrialização sem ninguém precisar mapear CFOP à mão.
 *
 * O DTO pode vir vazio ({@see encontrada()} falso): o OUTER APPLY da query 3
 * devolve a linha do item mesmo quando não há compra nenhuma no histórico.
 */
final readonly class UltimaCompraDTO
{
    public function __construct(
        public int $item,
        public ?int $material,
        public ?Carbon $data,
        public ?string $notaFiscal,
        public ?int $fornecedorCodigo,
        public ?string $fornecedorNome,
        public ?string $fornecedorFantasia,
        public ?string $fornecedorCnpj,
        public ?float $valorUnitario,
        public ?float $valorCusto,
        public ?string $unidade,
        public ?float $quantidade,
        public ?string $operacao,
        public ?int $filial,
        public ?float $variacaoVsCustoMedio,
    ) {
    }

    public static function deLinha(object $l): self
    {
        return new self(
            item: (int) $l->CD_ITEM,
            material: isset($l->CD_MATERIAL) && $l->CD_MATERIAL !== null ? (int) $l->CD_MATERIAL : null,
            data: filled($l->ULT_COMPRA_DATA ?? null) ? Carbon::parse($l->ULT_COMPRA_DATA) : null,
            notaFiscal: self::texto($l->ULT_COMPRA_NF ?? null),
            fornecedorCodigo: isset($l->ULT_COMPRA_CD_FORNECEDOR) && $l->ULT_COMPRA_CD_FORNECEDOR !== null
                ? (int) $l->ULT_COMPRA_CD_FORNECEDOR
                : null,
            fornecedorNome: self::texto($l->ULT_COMPRA_FORNECEDOR ?? null),
            fornecedorFantasia: self::texto($l->ULT_COMPRA_FORNECEDOR_FANTASIA ?? null),
            fornecedorCnpj: self::texto($l->ULT_COMPRA_CNPJ ?? null),
            valorUnitario: self::valor($l->ULT_COMPRA_VL_UNITARIO ?? null),
            valorCusto: self::valor($l->ULT_COMPRA_VL_CUSTO ?? null),
            unidade: self::texto($l->ULT_COMPRA_UN ?? null),
            quantidade: self::valor($l->ULT_COMPRA_QTD ?? null),
            operacao: self::texto($l->ULT_COMPRA_OPERACAO ?? null),
            filial: isset($l->ULT_COMPRA_FILIAL) && $l->ULT_COMPRA_FILIAL !== null
                ? (int) $l->ULT_COMPRA_FILIAL
                : null,
            variacaoVsCustoMedio: self::valor($l->VARIACAO_VS_CUSTO_MEDIO ?? null),
        );
    }

    /**
     * Achou compra anterior deste item?
     *
     * Falso em dois casos diferentes que a tela precisa distinguir e o cálculo
     * não: item sem cadastro (nunca teria histórico) e item cadastrado que
     * nunca foi comprado.
     */
    public function encontrada(): bool
    {
        return $this->valorUnitario !== null && $this->valorUnitario > 0;
    }

    /**
     * Nome curto para a coluna "ÚLT. COMPRA" — fantasia quando existe, porque é
     * como o comprador chama o fornecedor.
     */
    public function fornecedorCurto(): ?string
    {
        return $this->fornecedorFantasia ?: $this->fornecedorNome;
    }

    private static function texto(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function valor(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}

<?php

namespace App\Services\Questor\DTO;

use Illuminate\Support\Carbon;

/**
 * Um item da Solicitação de Compra (query 2) — vira uma linha do mapa.
 *
 * ARMADILHA CENTRAL DO MÓDULO: `material` (CD_MATERIAL) é NULO-ável.
 * TBL_COMPRAS_SOLICITACAO_ITENS aceita item digitado como texto livre em
 * DS_MATERIAL, sem cadastro em TBL_MATERIAIS. Esse item existe, é comprado, e
 * simplesmente não tem histórico por código.
 *
 * Por isso {@see semCadastro()}: a tela pergunta isso ao DTO em vez de comparar
 * `null` em cinco lugares diferentes e esquecer num deles.
 */
final readonly class SolicitacaoItemDTO
{
    public function __construct(
        public int $solicitacao,
        public int $item,
        public ?int $material,
        public string $descricao,
        public ?string $unidade,
        public float $quantidade,
        public ?float $valorReferencia,
        public ?string $observacoes,
        public ?string $almoxarifado,
        public ?int $centroCusto,
        public ?float $estoqueDisponivel,
        public ?float $custoMedio,
        public ?Carbon $ultimaCompraFilialData,
        public ?float $ultimaCompraFilialValor,
    ) {
    }

    public static function deLinha(object $l): self
    {
        return new self(
            solicitacao: (int) $l->CD_SOLICITACAO,
            item: (int) $l->CD_ITEM,
            // Nulo de verdade, não zero: zero seria um CD_MATERIAL válido.
            material: isset($l->CD_MATERIAL) && $l->CD_MATERIAL !== null ? (int) $l->CD_MATERIAL : null,
            descricao: trim((string) ($l->DS_MATERIAL ?? '')) ?: 'ITEM SEM DESCRIÇÃO',
            unidade: self::texto($l->DS_UNIDADE ?? null),
            quantidade: (float) ($l->NR_QUANTIDADE ?? 0),
            valorReferencia: self::valor($l->VL_UNITARIO ?? null),
            observacoes: self::texto($l->DS_OBS ?? null),
            almoxarifado: self::texto($l->DS_ALMOXARIFADO ?? null),
            centroCusto: isset($l->CD_CENTRO_CUSTO) ? (int) $l->CD_CENTRO_CUSTO : null,
            estoqueDisponivel: self::valor($l->NR_ESTOQUE_DISPONIVEL ?? null),
            custoMedio: self::valor($l->VL_CUSTO_MEDIO ?? null),
            ultimaCompraFilialData: filled($l->DT_ULTIMA_COMPRA_FILIAL ?? null)
                ? Carbon::parse($l->DT_ULTIMA_COMPRA_FILIAL)
                : null,
            ultimaCompraFilialValor: self::valor($l->VL_ULTIMA_COMPRA_FILIAL ?? null),
        );
    }

    /**
     * Item digitado como texto livre, sem cadastro em TBL_MATERIAIS.
     *
     * Não há histórico de compra por código para ele — e isso é normal. A linha
     * entra no mapa igual; só a área de histórico muda de conteúdo.
     */
    public function semCadastro(): bool
    {
        return $this->material === null;
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

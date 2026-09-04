<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma célula da grade: o que o fornecedor X respondeu sobre o item Y.
 *
 * O valor só é confiável quando `situacao` é `cotado`. Nas outras duas
 * situações `valor_unitario` é nulo — e nulo NÃO é zero em conta nenhuma. Ver
 * {@see \App\Services\Cotacao\MapaCalculoService}.
 */
class CotacaoPreco extends Model
{
    use HasFactory;

    public const SITUACAO_COTADO = 'cotado';
    public const SITUACAO_NAO_TRABALHA = 'nao_trabalha';
    public const SITUACAO_SEM_RESPOSTA = 'sem_resposta';

    public const SITUACOES = [
        self::SITUACAO_COTADO,
        self::SITUACAO_NAO_TRABALHA,
        self::SITUACAO_SEM_RESPOSTA,
    ];

    protected $fillable = [
        'cotacao_mapa_item_id',
        'cotacao_mapa_fornecedor_id',
        'valor_unitario',
        'situacao',
        'marca',
        'observacao',
    ];

    protected $casts = [
        'cotacao_mapa_item_id' => 'integer',
        'cotacao_mapa_fornecedor_id' => 'integer',
        'valor_unitario' => 'decimal:6',
    ];

    /**
     * @return BelongsTo<CotacaoMapaItem, CotacaoPreco>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CotacaoMapaItem::class, 'cotacao_mapa_item_id');
    }

    /**
     * @return BelongsTo<CotacaoMapaFornecedor, CotacaoPreco>
     */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(CotacaoMapaFornecedor::class, 'cotacao_mapa_fornecedor_id');
    }

    /**
     * Tem preço utilizável? `cotado` com valor nulo não conta — é estado
     * inconsistente que a validação impede de nascer, mas o cálculo não confia.
     */
    public function temPreco(): bool
    {
        return $this->situacao === self::SITUACAO_COTADO && $this->valor_unitario !== null;
    }

    /**
     * O "NT" da planilha. Separado de "não respondeu" de propósito: o
     * fornecedor que não vende o item não pode ser cobrado por não ter cotado.
     */
    public function naoTrabalha(): bool
    {
        return $this->situacao === self::SITUACAO_NAO_TRABALHA;
    }

    /**
     * Rótulo curto para a grade e para o XLSX. `sem_resposta` é string vazia
     * de propósito: no papel, célula em branco é a convenção da compra.
     */
    public function rotulo(): string
    {
        return match ($this->situacao) {
            self::SITUACAO_NAO_TRABALHA => 'NT',
            self::SITUACAO_COTADO => (string) $this->valor_unitario,
            default => '',
        };
    }
}

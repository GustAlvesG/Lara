<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma linha do mapa — um item a cotar.
 *
 * Vem da solicitação do Questor (`origem = solicitacao`) ou foi acrescentado à
 * mão pelo comprador (`origem = avulso`).
 *
 * `questor_cd_material` nulo NÃO é erro: a solicitação aceita item digitado
 * como texto livre, e nesse caso não existe histórico de compra por código. A
 * linha aparece no mapa igual, só sem a área de histórico.
 */
class CotacaoMapaItem extends Model
{
    use HasFactory;

    // Explícito: a pluralização automática do Laravel geraria
    // `cotacao_mapa_items`, e a tabela é `cotacao_mapa_itens`.
    protected $table = 'cotacao_mapa_itens';

    public const ORIGEM_SOLICITACAO = 'solicitacao';
    public const ORIGEM_AVULSO = 'avulso';

    protected $fillable = [
        'cotacao_mapa_id',
        'questor_cd_item',
        'questor_cd_material',
        'descricao',
        'unidade',
        'quantidade',
        'ordem',
        'origem',
        'ult_compra_data',
        'ult_compra_fornecedor_id',
        'ult_compra_fornecedor_nome',
        'ult_compra_valor',
        'ult_compra_nf',
        'ult_compra_unidade',
        'vencedor_id',
    ];

    protected $casts = [
        'cotacao_mapa_id' => 'integer',
        'questor_cd_item' => 'integer',
        'questor_cd_material' => 'integer',
        'quantidade' => 'decimal:4',
        'ordem' => 'integer',
        'ult_compra_data' => 'date',
        'ult_compra_fornecedor_id' => 'integer',
        'ult_compra_valor' => 'decimal:6',
        'vencedor_id' => 'integer',
    ];

    /**
     * @return BelongsTo<CotacaoMapa, CotacaoMapaItem>
     */
    public function mapa(): BelongsTo
    {
        return $this->belongsTo(CotacaoMapa::class, 'cotacao_mapa_id');
    }

    /**
     * @return HasMany<CotacaoPreco>
     */
    public function precos(): HasMany
    {
        return $this->hasMany(CotacaoPreco::class, 'cotacao_mapa_item_id');
    }

    /**
     * @return BelongsTo<CotacaoMapaFornecedor, CotacaoMapaItem>
     */
    public function vencedor(): BelongsTo
    {
        return $this->belongsTo(CotacaoMapaFornecedor::class, 'vencedor_id');
    }

    /**
     * O item existe no cadastro de materiais do Questor? É o que decide se o
     * drill-down tem histórico para mostrar ou a mensagem "item sem cadastro".
     */
    public function temCadastroNoQuestor(): bool
    {
        return $this->questor_cd_material !== null;
    }

    /**
     * Há retrato de última compra? Um item pode ter cadastro e mesmo assim
     * nunca ter sido comprado.
     */
    public function temUltimaCompra(): bool
    {
        return $this->ult_compra_valor !== null && (float) $this->ult_compra_valor > 0;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma coluna do mapa — o fornecedor consultado.
 *
 * `questor_cd_entidade` nulo é o fornecedor que ainda não está no cadastro do
 * ERP. O comprador pode acrescentar quem quiser à cotação sem esperar cadastro,
 * e é por isso que `nome` (texto) é o campo obrigatório, não o código.
 */
class CotacaoMapaFornecedor extends Model
{
    use HasFactory;

    protected $table = 'cotacao_mapa_fornecedores';

    protected $fillable = [
        'cotacao_mapa_id',
        'questor_cd_entidade',
        'nome',
        'cnpj',
        'contato',
        'email',
        'telefone',
        'frete',
        'prazo_entrega',
        'condicao_pagamento',
        'valor_frete',
        'desconto',
        'validade_proposta',
        'observacoes',
        'ordem',
    ];

    protected $casts = [
        'cotacao_mapa_id' => 'integer',
        'questor_cd_entidade' => 'integer',
        'valor_frete' => 'decimal:2',
        'desconto' => 'decimal:2',
        'validade_proposta' => 'date',
        'ordem' => 'integer',
    ];

    /**
     * @return BelongsTo<CotacaoMapa, CotacaoMapaFornecedor>
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
        return $this->hasMany(CotacaoPreco::class, 'cotacao_mapa_fornecedor_id');
    }

    /**
     * Itens em que este fornecedor foi escolhido.
     *
     * @return HasMany<CotacaoMapaItem>
     */
    public function itensVencidos(): HasMany
    {
        return $this->hasMany(CotacaoMapaItem::class, 'vencedor_id');
    }
}

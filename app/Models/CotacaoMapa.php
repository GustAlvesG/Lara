<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um mapa de cotação: a solicitação do Questor transformada em grade de
 * comparação de fornecedores.
 *
 * O mapa é da Lara inteiro. Do Questor vêm só os números de origem
 * (`questor_solicitacao`, `questor_empresa`, `questor_filial`) e os retratos de
 * texto — nada aqui volta para o ERP.
 *
 * @property int $id
 * @property int $questor_solicitacao
 * @property string $status
 */
class CotacaoMapa extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_EM_COTACAO = 'em_cotacao';
    public const STATUS_FECHADO = 'fechado';
    public const STATUS_CANCELADO = 'cancelado';

    /**
     * Os estados em que o mapa ainda ocupa a solicitação. É o que a importação
     * consulta antes de criar um segundo mapa para a mesma SC.
     */
    public const STATUS_ABERTOS = [
        self::STATUS_RASCUNHO,
        self::STATUS_EM_COTACAO,
        self::STATUS_FECHADO,
    ];

    protected $fillable = [
        'questor_solicitacao',
        'questor_empresa',
        'questor_filial',
        'titulo',
        'solicitante',
        'departamento',
        'comprador',
        'data_mapa',
        'status',
        'observacoes',
        'user_id',
    ];

    protected $casts = [
        'questor_solicitacao' => 'integer',
        'questor_empresa' => 'integer',
        'questor_filial' => 'integer',
        'data_mapa' => 'date',
        'user_id' => 'integer',
    ];

    /**
     * @return HasMany<CotacaoMapaItem>
     */
    public function itens(): HasMany
    {
        return $this->hasMany(CotacaoMapaItem::class)->orderBy('ordem')->orderBy('id');
    }

    /**
     * @return HasMany<CotacaoMapaFornecedor>
     */
    public function fornecedores(): HasMany
    {
        return $this->hasMany(CotacaoMapaFornecedor::class)->orderBy('ordem')->orderBy('id');
    }

    /**
     * @return HasMany<CotacaoMapaLog>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CotacaoMapaLog::class)->latest('created_at');
    }

    /**
     * Todos os preços do mapa, sem passar pelos itens — é assim que a grade
     * carrega a matriz inteira numa consulta só.
     *
     * @return HasManyThrough<CotacaoPreco>
     */
    public function precos(): HasManyThrough
    {
        return $this->hasManyThrough(
            CotacaoPreco::class,
            CotacaoMapaItem::class,
            'cotacao_mapa_id',
            'cotacao_mapa_item_id'
        );
    }

    /**
     * Mapa fechado ou cancelado não aceita edição. A checagem mora aqui para
     * controller, policy e serviço concordarem sobre o que é "somente leitura".
     */
    public function editavel(): bool
    {
        return in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_EM_COTACAO], true);
    }

    public function fechado(): bool
    {
        return $this->status === self::STATUS_FECHADO;
    }

    /**
     * Mapas que ainda "ocupam" a solicitação — os que a importação encontra
     * antes de oferecer duplicar o trabalho.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<CotacaoMapa>  $query
     */
    public function scopeAbertos($query)
    {
        return $query->whereIn('status', self::STATUS_ABERTOS);
    }

    /**
     * Rótulo do estado para a tela, sem espalhar `match` por cinco views.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_EM_COTACAO => 'Em cotação',
            self::STATUS_FECHADO => 'Fechado',
            self::STATUS_CANCELADO => 'Cancelado',
            default => $this->status,
        };
    }
}

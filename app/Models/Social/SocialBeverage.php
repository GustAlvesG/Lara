<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item do catálogo das reservas sociais: bebida ou mobiliário (mesa avulsa,
 * cadeira avulsa, jogo completo). O nome da classe é de quando o catálogo era
 * só de bebidas; a categoria diz o que é.
 *
 * `mv_code` é o código do produto no MV, cadastrado à mão — o Lara não fala
 * com o MV. No mobiliário, `tables_per_unit`/`chairs_per_unit` dizem quantas
 * mesas e cadeiras cada unidade acrescenta (jogo completo = 1 + 4) e
 * `max_quantity` limita a quantidade por reserva (vazio = sem limite).
 */
class SocialBeverage extends Model
{
    use SoftDeletes;

    public const CATEGORY_BEVERAGE = 'bebida';
    public const CATEGORY_FURNITURE = 'mobiliario';

    /** @var array<string, string> */
    public const CATEGORIES = [
        self::CATEGORY_BEVERAGE => 'Bebida',
        self::CATEGORY_FURNITURE => 'Mesas e cadeiras',
    ];

    protected $fillable = [
        'name',
        'category',
        'unit',
        'mv_code',
        'price',
        'tables_per_unit',
        'chairs_per_unit',
        'max_quantity',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tables_per_unit' => 'integer',
        'chairs_per_unit' => 'integer',
        'max_quantity' => 'integer',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'category' => self::CATEGORY_BEVERAGE,
    ];

    public function scopeBeverages(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_BEVERAGE);
    }

    public function scopeFurniture(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_FURNITURE);
    }

    public function isFurniture(): bool
    {
        return $this->category === self::CATEGORY_FURNITURE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

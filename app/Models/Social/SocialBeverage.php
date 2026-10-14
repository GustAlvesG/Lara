<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item do catálogo de bebidas das reservas sociais. `mv_code` é o código do
 * produto no MV, cadastrado à mão — o Lara não fala com o MV.
 */
class SocialBeverage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'unit',
        'mv_code',
        'price',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

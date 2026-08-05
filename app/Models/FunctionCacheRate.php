<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * O valor de uma faixa de horas do cachê (2h, 3h, ..., 11h).
 *
 * É uma linha por faixa, e não uma fórmula, porque o cachê não é proporcional:
 * cada faixa tem seu valor negociado.
 */
class FunctionCacheRate extends Model
{
    use HasFactory;

    protected $table = 'function_cache_rates';

    protected $fillable = [
        'function_freelancer_id',
        'hours',
        'price',
    ];

    protected $casts = [
        'hours' => 'integer',
        'price' => 'decimal:2',
    ];

    public function function()
    {
        return $this->belongsTo(FunctionFreelancer::class, 'function_freelancer_id');
    }

    /** Ex.: "4h". */
    public function label(): string
    {
        return $this->hours . 'h'
            . ($this->hours >= FunctionFreelancer::CACHE_MAX_HOURS ? ' ou mais' : '');
    }
}

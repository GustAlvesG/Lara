<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase da disputa de uma categoria: grupos ou mata-mata. Na eliminatória
 * simples há uma fase só; em grupos + mata-mata, duas (`order` 1 e 2).
 * `generation` diz como o chaveamento foi montado: sorteio ou à mão.
 */
class TournamentStage extends Model
{
    public const TYPE_GROUP = 'group';
    public const TYPE_KNOCKOUT = 'knockout';

    public const GENERATION_DRAW = 'draw';
    public const GENERATION_MANUAL = 'manual';

    protected $fillable = [
        'tournament_category_id',
        'type',
        'order',
        'generation',
        'generated_at',
    ];

    protected $casts = [
        'order' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class, 'tournament_category_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class)->orderBy('order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('position');
    }
}

<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grupo da fase de grupos ("Grupo A"): todos contra todos. */
class TournamentGroup extends Model
{
    protected $fillable = [
        'tournament_stage_id',
        'name',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'tournament_stage_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TournamentGroupTeam::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('position');
    }
}

<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um set do jogo: games de cada lado e, se houve, o tie-break. */
class TournamentMatchSet extends Model
{
    protected $fillable = [
        'tournament_match_id',
        'set_number',
        'home_games',
        'away_games',
        'home_tiebreak',
        'away_tiebreak',
    ];

    protected $casts = [
        'set_number' => 'integer',
        'home_games' => 'integer',
        'away_games' => 'integer',
        'home_tiebreak' => 'integer',
        'away_tiebreak' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }
}

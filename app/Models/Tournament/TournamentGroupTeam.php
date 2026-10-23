<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time num grupo. `final_position` é a ordem que o organizador define ao
 * fechar o grupo: o sistema mostra vitórias, sets e games, mas empate na
 * classificação é resolvido fora dele.
 */
class TournamentGroupTeam extends Model
{
    protected $fillable = [
        'tournament_group_id',
        'tournament_team_id',
        'final_position',
    ];

    protected $casts = [
        'tournament_group_id' => 'integer',
        'tournament_team_id' => 'integer',
        'final_position' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'tournament_team_id');
    }
}

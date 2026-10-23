<?php

namespace App\Models\Tournament;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma inscrição dentro de um time. Uma inscrição fica em um time só (índice
 * único); sair do time apaga a linha. Quem pôs: o organizador
 * (`added_by_user_id`) ou o sócio, pelo site (`added_by_member_id`).
 */
class TournamentTeamMember extends Model
{
    protected $fillable = [
        'tournament_team_id',
        'tournament_registration_id',
        'added_by_user_id',
        'added_by_member_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'tournament_team_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }

    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function addedByMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'added_by_member_id');
    }
}

<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'member_id'];

    // O dono/criador da equipe
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    // Os jogadores que compõem a equipe
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_member')->withTimestamps();
    }

    // Inscrições desta equipe em torneios
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TournamentSubscription::class);
    }
}





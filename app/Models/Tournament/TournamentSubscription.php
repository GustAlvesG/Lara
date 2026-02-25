<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentSubscription extends Model
{
    // Informa o nome exato da tabela (sem o 's' no final)
    protected $table = 'tournament_subscription';

    protected $fillable = ['team_id', 'tournament_category_id', 'status_id'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class, 'tournament_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TournamentSubscriptionPayment::class, 'tournament_subscription_id');
    }
}
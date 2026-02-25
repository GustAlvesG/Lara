<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentSubscriptionPayment extends Model
{
    // Informa o nome exato da tabela
    protected $table = 'tournament_subscription_payment';

    protected $fillable = [
        'tournament_subscription_id', 'payment_method', 
        'paid_amount', 'payment_integration_id', 'paid_at', 'status_id'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TournamentSubscription::class, 'tournament_subscription_id');
    }
}
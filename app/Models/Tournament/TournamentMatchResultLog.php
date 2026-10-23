<?php

namespace App\Models\Tournament;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de resultado: cada lançamento ou correção, com a origem (Lara ou
 * site), quem lançou e o que foi gravado (`payload`: sets e vencedor). Só
 * se acrescenta, nunca se altera.
 */
class TournamentMatchResultLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tournament_match_id',
        'origin',
        'user_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

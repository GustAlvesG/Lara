<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Time de uma categoria, formado depois das inscrições: pelo organizador,
 * por sorteio ou pelo capitão (`origin`). Categoria individual tem um time
 * por inscrito.
 *
 * O capitão é opcional; o organizador pode trocar ou retirar. Time
 * incompleto é resolvido caso a caso pelo organizador.
 */
class TournamentTeam extends Model
{
    public const STATUS_FORMING = 'forming';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_FORMING => 'Em formação',
        self::STATUS_COMPLETE => 'Completo',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const ORIGIN_ORGANIZER = TournamentCategory::FORMATION_ORGANIZER;
    public const ORIGIN_DRAW = TournamentCategory::FORMATION_DRAW;
    public const ORIGIN_CAPTAIN = TournamentCategory::FORMATION_CAPTAIN;
    /** Time de uma pessoa, criado sozinho na categoria individual. */
    public const ORIGIN_INDIVIDUAL = 'individual';

    protected $fillable = [
        'tournament_category_id',
        'name',
        'captain_registration_id',
        'origin',
        'status',
        'seed',
    ];

    protected $casts = [
        'seed' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_FORMING,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class, 'tournament_category_id');
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'captain_registration_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TournamentTeamMember::class);
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(TournamentRegistration::class, 'tournament_team_members')->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(TournamentTeamInvite::class);
    }
}

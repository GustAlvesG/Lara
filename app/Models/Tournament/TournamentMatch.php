<?php

namespace App\Models\Tournament;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jogo de uma fase. No mata-mata o vencedor segue para `next_match_id`, no
 * lado `next_slot` (`home`/`away`); um lado que vem da fase de grupos diz a
 * origem em `*_source_group_id` + `*_source_position` até o grupo fechar.
 *
 * Placar por sets e games (TournamentMatchSet), sem empate. `home_sets` e
 * `away_sets` são o resumo gravado junto. O resultado pode vir do Lara ou do
 * site (equipe do clube); lançado no Lara, `locked_by_lara` trava o jogo para
 * o site — o Lara sempre prevalece.
 */
class TournamentMatch extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_WALKOVER = 'walkover';
    /** Lado sem adversário no mata-mata: o time passa direto. */
    public const STATUS_BYE = 'bye';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'A jogar',
        self::STATUS_FINISHED => 'Encerrado',
        self::STATUS_WALKOVER => 'W.O.',
        self::STATUS_BYE => 'Passou direto',
    ];

    public const SLOT_HOME = 'home';
    public const SLOT_AWAY = 'away';

    public const ORIGIN_LARA = 'lara';
    public const ORIGIN_SITE = 'site';

    protected $fillable = [
        'tournament_stage_id',
        'tournament_group_id',
        'round',
        'position',
        'home_team_id',
        'away_team_id',
        'next_match_id',
        'next_slot',
        'third_place',
        'home_source_group_id',
        'home_source_position',
        'away_source_group_id',
        'away_source_position',
        'scheduled_at',
        'location_note',
        'status',
        'home_sets',
        'away_sets',
        'winner_team_id',
        'locked_by_lara',
        'result_origin',
        'result_user_id',
        'result_at',
    ];

    protected $casts = [
        'tournament_stage_id' => 'integer',
        'tournament_group_id' => 'integer',
        'round' => 'integer',
        'position' => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
        'next_match_id' => 'integer',
        'home_source_group_id' => 'integer',
        'away_source_group_id' => 'integer',
        'winner_team_id' => 'integer',
        'home_source_position' => 'integer',
        'away_source_position' => 'integer',
        'scheduled_at' => 'datetime',
        'home_sets' => 'integer',
        'away_sets' => 'integer',
        'third_place' => 'boolean',
        'locked_by_lara' => 'boolean',
        'result_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'tournament_stage_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'winner_team_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(TournamentMatchSet::class)->orderBy('set_number');
    }

    public function resultLogs(): HasMany
    {
        return $this->hasMany(TournamentMatchResultLog::class)->orderBy('id');
    }

    public function resultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_user_id');
    }
}

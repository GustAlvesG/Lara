<?php

namespace App\Models\Tournament;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convite do capitão para o time (categorias com montagem pelo capitão).
 *
 * Guarda o CPF, não o sócio: o convidado pode ainda não ter cadastro no site
 * de locação. Pela matrícula, o capitão escolhe a pessoa entre os membros do
 * título. Ao aceitar sem estar inscrito, a pessoa se inscreve e paga; a
 * inscrição criada fica em `tournament_registration_id`.
 */
class TournamentTeamInvite extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Aguardando resposta',
        self::STATUS_ACCEPTED => 'Aceito',
        self::STATUS_DECLINED => 'Recusado',
        self::STATUS_CANCELLED => 'Cancelado',
        self::STATUS_EXPIRED => 'Expirado',
    ];

    protected $fillable = [
        'tournament_team_id',
        'invited_by_member_id',
        'cpf',
        'title',
        'name',
        'email',
        'status',
        'tournament_registration_id',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'tournament_team_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'invited_by_member_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }
}

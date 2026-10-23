<?php

namespace App\Models\Tournament;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Inscrição individual: uma pessoa numa categoria do torneio. O time vem
 * depois (TournamentTeamMember).
 *
 * CPF, matrícula, nome, e-mail, nascimento e sexo são cópia do MultiClubes no
 * momento da inscrição. `amount` é o preço congelado.
 *
 * A pessoa tem no máximo uma inscrição ativa (`ACTIVE_STATUSES`) por
 * categoria; quem garante é o service, com trava de linha.
 */
class TournamentRegistration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING_PAYMENT => 'Aguardando pagamento',
        self::STATUS_PAID => 'Paga',
        self::STATUS_WITHDRAWN => 'Desistiu',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const ACTIVE_STATUSES = [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID];

    protected $fillable = [
        'tournament_category_id',
        'member_id',
        'cpf',
        'title',
        'name',
        'email',
        'birth_date',
        'gender',
        'amount',
        'status',
        'eligibility_override_by',
        'eligibility_override_reason',
        'tournament_payment_id',
        'paid_at',
        'withdrawn_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'refunded_amount',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING_PAYMENT,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class, 'tournament_category_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TournamentPayment::class, 'tournament_payment_id');
    }

    public function teamMembership(): HasOne
    {
        return $this->hasOne(TournamentTeamMember::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eligibility_override_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}

<?php

namespace App\Models\Tournament;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pagamento de inscrições de torneio: eRede (`payment_integration_id` = tid)
 * ou baixa manual. Um pagamento cobre uma ou mais inscrições da mesma pessoa.
 * Mesmo desenho do SchedulePayment, em tabela própria para não misturar
 * torneio com reserva nos relatórios e no estorno.
 *
 * `is_manual`, `recorded_by` e `manual_notes` ficam fora do fillable: só o
 * service da baixa manual grava, nunca a API.
 */
class TournamentPayment extends Model
{
    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PAID => 'Pago',
        self::STATUS_REFUNDED => 'Estornado',
        self::STATUS_PARTIALLY_REFUNDED => 'Estornado em parte',
    ];

    protected $fillable = [
        'member_id',
        'payment_method',
        'paid_amount',
        'paid_at',
        'payment_integration_id',
        'status',
        'refunded_amount',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_PAID,
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

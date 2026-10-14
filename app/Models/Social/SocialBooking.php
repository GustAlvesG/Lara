<?php

namespace App\Models\Social;

use App\Models\Member;
use App\Models\Place;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reserva de espaço social (churrasqueira, salão, área de festa).
 *
 * O fluxo é diferente do da quadra: o sócio faz a pré-reserva do dia, a equipe
 * faz o contrato fora do sistema, marca "contrato ok" e aprova (com desconto,
 * se for o caso); só então o pagamento é liberado no app, com prazo. Pago,
 * está confirmado. As regras moram em SocialBookingService.
 *
 * A ocupação do dia é o `schedule` ligado (00:00 às 23:59:59). O status dele
 * acompanha o estágio — ver SCHEDULE_STATUS — para que agenda, colisão,
 * pagamento e estorno continuem funcionando pelo caminho de sempre.
 */
class SocialBooking extends Model
{
    /** Pré-reserva enviada; a data fica presa enquanto a equipe analisa. */
    public const STAGE_REQUESTED = 'requested';

    /** Aprovada: valor final e prazo definidos, pagamento liberado no app. */
    public const STAGE_AWAITING_PAYMENT = 'awaiting_payment';

    public const STAGE_CONFIRMED = 'confirmed';
    public const STAGE_REFUSED = 'refused';
    public const STAGE_CANCELLED = 'cancelled';

    /** O prazo de pagamento venceu; a data voltou a ficar livre. */
    public const STAGE_EXPIRED = 'expired';

    /** Estágios que ocupam a data. */
    public const ACTIVE_STAGES = [
        self::STAGE_REQUESTED,
        self::STAGE_AWAITING_PAYMENT,
        self::STAGE_CONFIRMED,
    ];

    /** @var array<string, string> */
    public const STAGE_LABELS = [
        self::STAGE_REQUESTED => 'Em análise',
        self::STAGE_AWAITING_PAYMENT => 'Aguardando pagamento',
        self::STAGE_CONFIRMED => 'Confirmada',
        self::STAGE_REFUSED => 'Recusada',
        self::STAGE_CANCELLED => 'Cancelada',
        self::STAGE_EXPIRED => 'Expirada',
    ];

    /**
     * status_id do schedule em cada estágio: 3 (pendente) enquanto não paga,
     * 1 pago, 0 recusado/cancelado, 4 expirado — o mesmo vocabulário das
     * quadras, para quem só olha o schedule.
     */
    public const SCHEDULE_STATUS = [
        self::STAGE_REQUESTED => 3,
        self::STAGE_AWAITING_PAYMENT => 3,
        self::STAGE_CONFIRMED => 1,
        self::STAGE_REFUSED => 0,
        self::STAGE_CANCELLED => 0,
        self::STAGE_EXPIRED => 4,
    ];

    protected $fillable = [
        'schedule_id',
        'place_id',
        'member_id',
        'event_date',
        'stage',
        'event_type',
        'expected_guests',
        'notes',
        'base_price',
        'extra_guests',
        'extra_guest_price',
        'extras_total',
        'items_total',
        'discount',
        'discount_reason',
        'total',
        'contract_ok_at',
        'contract_ok_by',
        'contract_file',
        'approved_at',
        'approved_by',
        'payment_due_at',
        'refused_at',
        'refused_by',
        'refusal_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'created_by_user',
    ];

    protected $casts = [
        'event_date' => 'date',
        'expected_guests' => 'integer',
        'base_price' => 'decimal:2',
        'extra_guests' => 'integer',
        'extra_guest_price' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'items_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'contract_ok_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'refused_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function schedule()
    {
        // Sem o scope "not_expired": a reserva expirada continua tendo ocupação.
        return $this->belongsTo(Schedule::class)->withoutGlobalScopes();
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function items()
    {
        return $this->hasMany(SocialBookingItem::class);
    }

    public function guests()
    {
        return $this->hasMany(SocialBookingGuest::class)->orderBy('name');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function contractChecker()
    {
        return $this->belongsTo(User::class, 'contract_ok_by');
    }

    public function refuser()
    {
        return $this->belongsTo(User::class, 'refused_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('stage', self::ACTIVE_STAGES);
    }

    public function stageLabel(): string
    {
        return self::STAGE_LABELS[$this->stage] ?? $this->stage;
    }

    /** Selo do estágio no painel (`x-pill`). */
    public function stageKind(): string
    {
        return match ($this->stage) {
            self::STAGE_CONFIRMED => 'ok',
            self::STAGE_REQUESTED => 'info',
            self::STAGE_AWAITING_PAYMENT => 'warn',
            self::STAGE_REFUSED, self::STAGE_CANCELLED => 'danger',
            default => 'off',
        };
    }

    public function isActive(): bool
    {
        return in_array($this->stage, self::ACTIVE_STAGES, true);
    }

    /** Ainda não houve pagamento: o sócio pode desistir e a equipe pode editar. */
    public function isUnpaid(): bool
    {
        return in_array($this->stage, [self::STAGE_REQUESTED, self::STAGE_AWAITING_PAYMENT], true);
    }

    /** Quantos não sócios podem estar na lista: os incluídos no preço + os extras contratados. */
    public function guestLimit(): int
    {
        return (int) ($this->place?->max_guests ?? 0) + (int) $this->extra_guests;
    }

    /** Valor antes do desconto: espaço + convidados extras + bebidas. */
    public function grossTotal(): float
    {
        return round((float) $this->base_price + (float) $this->extras_total + (float) $this->items_total, 2);
    }

    /**
     * Pré-reserva ainda editável (tudo menos data e espaço), pela equipe ou
     * pelo sócio: em análise e antes do "contrato ok". O contrato fecha o que
     * foi combinado — depois dele não há mais trocas.
     */
    public function isEditable(): bool
    {
        return $this->stage === self::STAGE_REQUESTED && $this->contract_ok_at === null;
    }

    public function contractOk(): bool
    {
        return $this->contract_ok_at !== null;
    }

    /**
     * Até quando o sócio mexe na lista de convidados pelo app: até o fim da
     * véspera. No dia, a portaria já está com a lista; mudança é pela equipe.
     */
    public function guestsEditableUntil(): Carbon
    {
        return Carbon::parse($this->event_date)->subDay()->endOfDay();
    }

    public function canMemberEditGuests(?Carbon $now = null): bool
    {
        return $this->isActive() && ($now ?? Carbon::now())->lte($this->guestsEditableUntil());
    }

    public function canPay(?Carbon $now = null): bool
    {
        return $this->stage === self::STAGE_AWAITING_PAYMENT
            && ($this->payment_due_at === null || ($now ?? Carbon::now())->lte($this->payment_due_at));
    }
}

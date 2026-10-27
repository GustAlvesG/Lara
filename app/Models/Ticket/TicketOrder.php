<?php

namespace App\Models\Ticket;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Pedido de ingressos.
 *
 *   criar → `pending_payment` (segura o estoque até `hold_until`)
 *         → (eRede pelo site | baixa manual) → `paid`
 *   saídas: `expired` (passou o prazo sem pagar), `withdrawn` (quem comprou
 *   desistiu antes de pagar), `cancelled` (a equipe cancelou todos os
 *   ingressos do pedido pago)
 *
 * `buyer_kind` = `member` (sócio logado, para as pessoas do título dele) ou `guest`
 * (não sócio sem conta; acessa o pedido pelo link com `access_token`).
 *
 * `access_token` é cifrado e fica fora do fillable e do JSON: só o
 * TicketOrderService grava, e só o e-mail e a criação o mostram.
 */
class TicketOrder extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING_PAYMENT => 'Aguardando pagamento',
        self::STATUS_PAID => 'Pago',
        self::STATUS_EXPIRED => 'Expirado',
        self::STATUS_WITHDRAWN => 'Desistência',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const BUYER_MEMBER = 'member';
    public const BUYER_GUEST = 'guest';

    /** @var array<string, string> */
    public const BUYER_LABELS = [
        self::BUYER_MEMBER => 'Sócio',
        self::BUYER_GUEST => 'Não sócio',
    ];

    public const ORIGIN_SITE = 'site';
    public const ORIGIN_COUNTER = 'secretaria';

    protected $fillable = [
        'ticket_event_id',
        'buyer_kind',
        'member_id',
        'buyer_cpf',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_title',
        'total',
        'ticket_coupon_id',
        'coupon_code',
        'discount_total',
        'status',
        'hold_until',
        'origin',
        'courtesy',
        'created_by',
        'paid_at',
        'expired_at',
        'withdrawn_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'ticket_event_id' => 'integer',
        'member_id' => 'integer',
        'total' => 'decimal:2',
        'ticket_coupon_id' => 'integer',
        'discount_total' => 'decimal:2',
        'courtesy' => 'boolean',
        'hold_until' => 'datetime',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'access_token' => 'encrypted',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING_PAYMENT,
        'origin' => self::ORIGIN_SITE,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class, 'ticket_event_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(TicketPayment::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(TicketCoupon::class, 'ticket_coupon_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isGuest(): bool
    {
        return $this->buyer_kind === self::BUYER_GUEST;
    }

    /** Pendente e ainda dentro do prazo: segura o estoque. */
    public function holding(?Carbon $at = null): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->hold_until !== null
            && ($at ?? now())->lessThan($this->hold_until);
    }

    /** Confere o token do link do não sócio, sem vazar tempo de comparação. */
    public function tokenMatches(?string $token): bool
    {
        return $this->isGuest()
            && filled($token)
            && filled($this->access_token)
            && hash_equals((string) $this->access_token, (string) $token);
    }
}

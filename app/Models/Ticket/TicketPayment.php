<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento de um pedido de ingressos: eRede (`payment_integration_id` = tid)
 * ou baixa manual. Mesmo desenho do TournamentPayment, em tabela própria.
 *
 * `is_manual`, `recorded_by` e `manual_notes` ficam fora do fillable: só a
 * baixa manual grava, nunca a API.
 */
class TicketPayment extends Model
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
        'ticket_order_id',
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
        'ticket_order_id' => 'integer',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_PAID,
        'refunded_amount' => 0,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
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

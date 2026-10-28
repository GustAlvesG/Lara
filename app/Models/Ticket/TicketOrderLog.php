<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de um pedido de ingressos: quem fez o quê e quando. `user_id`
 * nulo = o site (quem comprou) ou uma rotina (expiração). Só se grava, nunca
 * se edita: o rastreio de quem emitiu, cobrou, trocou, cancelou e imprimiu.
 *
 * `details` guarda o que ajuda a ler depois (forma de pagamento, valor,
 * titular de antes e de depois, lugar de antes e de depois, via impressa).
 */
class TicketOrderLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED = 'created';
    public const PAID_ONLINE = 'paid_online';
    public const PAID_MANUAL = 'paid_manual';
    public const HOLDER_CHANGED = 'holder_changed';
    public const SEAT_CHANGED = 'seat_changed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const WITHDRAWN = 'withdrawn';
    public const EXPIRED = 'expired';
    public const EMAIL_RESENT = 'email_resent';
    public const PRINTED = 'printed';

    /** @var array<string, string> */
    public const LABELS = [
        self::CREATED => 'Pedido criado',
        self::PAID_ONLINE => 'Pago pela eRede (site)',
        self::PAID_MANUAL => 'Pagamento recebido (baixa manual)',
        self::HOLDER_CHANGED => 'Titular trocado',
        self::SEAT_CHANGED => 'Lugar trocado',
        self::CANCELLED => 'Ingresso cancelado',
        self::REFUNDED => 'Estorno na eRede',
        self::WITHDRAWN => 'Desistência pelo site',
        self::EXPIRED => 'Expirado (prazo vencido)',
        self::EMAIL_RESENT => 'E-mail reenviado',
        self::PRINTED => 'Ingresso impresso',
    ];

    protected $fillable = [
        'ticket_order_id',
        'ticket_id',
        'user_id',
        'action',
        'details',
    ];

    protected $casts = [
        'ticket_order_id' => 'integer',
        'ticket_id' => 'integer',
        'user_id' => 'integer',
        'details' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Grava uma linha do histórico. */
    public static function record(TicketOrder|int $order, string $action, ?User $user = null, array $details = [], Ticket|int|null $ticket = null): self
    {
        return self::create([
            'ticket_order_id' => $order instanceof TicketOrder ? $order->id : $order,
            'ticket_id' => $ticket instanceof Ticket ? $ticket->id : $ticket,
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details === [] ? null : $details,
        ]);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    /** O detalhe em uma linha, para a tela do pedido. */
    public function summary(): string
    {
        $d = $this->details ?? [];
        $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

        return match ($this->action) {
            self::CREATED => trim(implode(' · ', array_filter([
                ($d['origin'] ?? null) === TicketOrder::ORIGIN_COUNTER ? 'no Lara' : 'no site',
                ! empty($d['courtesy']) ? 'cortesia' : null,
                isset($d['tickets']) ? $d['tickets'] . ' ingresso(s)' : null,
                isset($d['total']) && empty($d['courtesy']) ? $money($d['total']) : null,
            ]))),
            self::PAID_ONLINE, self::PAID_MANUAL => trim(implode(' · ', array_filter([
                $d['method_label'] ?? null,
                isset($d['amount']) ? $money($d['amount']) : null,
                $d['notes'] ?? null,
            ]))),
            self::HOLDER_CHANGED => ($d['from'] ?? '?') . ' → ' . ($d['to'] ?? '?'),
            self::SEAT_CHANGED => 'lugar ' . ($d['from'] ?? '?') . ' → ' . ($d['to'] ?? '?'),
            self::CANCELLED => trim(implode(' · ', array_filter([
                $d['holder'] ?? null,
                isset($d['amount']) ? $money($d['amount']) : null,
                isset($d['reason']) ? 'motivo: ' . $d['reason'] : null,
            ]))),
            self::REFUNDED => isset($d['amount']) ? $money($d['amount']) : '',
            self::PRINTED => trim(implode(' · ', array_filter([
                $d['holder'] ?? null,
                isset($d['copy']) ? ($d['copy'] > 1 ? "{$d['copy']}ª via" : '1ª via') : null,
            ]))),
            self::EMAIL_RESENT => (string) ($d['email'] ?? ''),
            default => '',
        };
    }
}

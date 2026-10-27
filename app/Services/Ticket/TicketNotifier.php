<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketOrder;
use App\Services\EmailService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * E-mails dos pedidos de ingresso, num template só (`emails.tickets.status`),
 * que muda título, cor e texto pelo tipo.
 *
 * Vai para quem comprou (o sócio, ou o não sócio no e-mail que ele digitou).
 * O e-mail do pedido pago lista os ingressos com os códigos e, para o não
 * sócio, leva o link do pedido no site (config `tickets.site_url`). Envio é
 * efeito colateral — falhou, registra e segue.
 */
class TicketNotifier
{
    public const PAID = 'tickets.paid';
    public const CANCELLED = 'tickets.cancelled';
    public const LINK = 'tickets.link';
    public const ACCESS_CODE = 'tickets.access_code';

    public const TYPES = [self::PAID, self::CANCELLED, self::LINK, self::ACCESS_CODE];

    private const TITLES = [
        self::PAID => 'Ingressos confirmados',
        self::CANCELLED => 'Ingresso cancelado',
        self::LINK => 'Seu pedido de ingressos',
    ];

    public function __construct(private ?EmailService $email = null)
    {
        $this->email ??= new EmailService();
    }

    /**
     * @param  Collection<int, Ticket>|null  $tickets  os ingressos de que o e-mail trata (padrão: todos do pedido)
     */
    public function notify(TicketOrder $order, string $type, ?Collection $tickets = null, array $extra = []): bool
    {
        try {
            $data = $this->build($order, $type, $tickets, $extra);
        } catch (\Throwable $e) {
            Log::error('Falha ao montar o e-mail de ingressos.', ['type' => $type, 'order_id' => $order->id, 'error' => $e->getMessage()]);

            return false;
        }

        return $this->email->sendScheduleMail($data);
    }

    public function build(TicketOrder $order, string $type, ?Collection $tickets = null, array $extra = []): array
    {
        $order->loadMissing('event', 'tickets.type', 'tickets.lot');
        $event = $order->event;
        $tickets ??= $order->tickets;
        $money = fn ($value) => number_format((float) $value, 2, ',', '.');
        $title = self::TITLES[$type] ?? 'Ingressos';

        return [
            'email' => $order->buyer_email,
            'name' => $order->buyer_name,
            'type' => $type,
            'subject' => $title . ' - ' . $event->title,
            'title' => $title,
            // EmailService registra estes ids no log quando o envio falha.
            'schedule_ids' => [$order->id],
            'event' => $event->title,
            'venue' => $event->venue,
            'dates' => $event->datesLabel(),
            'order_id' => $order->id,
            'tickets' => $tickets->map(fn (Ticket $t) => [
                'holder' => $t->holder_name,
                'type' => $t->type?->fullName(),
                'lot' => $t->lot?->name,
                'amount' => $money($t->amount),
                // O código só aparece em ingresso pago: o pendente ainda não vale.
                'code' => in_array($t->status, Ticket::SOLD, true) ? $t->code : null,
                'status' => Ticket::STATUS_LABELS[$t->status] ?? $t->status,
            ])->values()->all(),
            'total' => $money($tickets->sum('amount')),
            'discount' => (float) $tickets->sum('discount_amount') > 0 ? $money($tickets->sum('discount_amount')) : null,
            'coupon_code' => $order->coupon_code,
            'courtesy' => (bool) $order->courtesy,
            'link' => $this->orderLink($order),
            'cancel_reason' => $type === self::CANCELLED ? ($extra['reason'] ?? null) : null,
            'refunded' => ! empty($extra['refunded']) ? $money($extra['refunded']) : null,
            'issued_at' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * O código de "meus ingressos" do não sócio (TicketGuestAccessService).
     * Template próprio: não é de um pedido só.
     */
    public function accessCode(string $email, string $name, string $code, int $minutes): bool
    {
        return $this->email->sendScheduleMail([
            'email' => $email,
            'name' => $name,
            'type' => self::ACCESS_CODE,
            'subject' => "Seu código de acesso aos ingressos: {$code}",
            'code' => $code,
            'minutes' => $minutes,
            'schedule_ids' => [],
        ]);
    }

    /** O link do pedido do não sócio no site; null para sócio ou sem `tickets.site_url`. */
    public function orderLink(TicketOrder $order): ?string
    {
        $site = rtrim((string) config('tickets.site_url'), '/');

        if (! $order->isGuest() || $site === '' || blank($order->access_token)) {
            return null;
        }

        return "{$site}/ingressos/pedido/{$order->id}?token=" . urlencode($order->access_token);
    }
}

<?php

namespace App\Services\Social;

use App\Models\Member;
use App\Models\Social\SocialBooking;
use App\Services\EmailService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * E-mails da reserva social: um por mudança de estágio, todos no mesmo
 * template (`emails.social.status`), que muda título, cor e texto pelo tipo.
 *
 * Não passa por SchedulesService::notifyScheduleStatus: aquele monta e-mail de
 * horário de quadra ("das 00:00 às 23:59", hold de 10 minutos), que não
 * descreve uma reserva social. Envio é efeito colateral — falhou, registra e
 * segue; a reserva já está gravada.
 */
class SocialBookingNotifier
{
    public const REQUESTED = 'social.requested';
    public const APPROVED = 'social.approved';
    public const REFUSED = 'social.refused';
    public const CONFIRMED = 'social.confirmed';
    public const EXPIRED = 'social.expired';
    public const CANCELLED = 'social.cancelled';

    private const TITLES = [
        self::REQUESTED => 'Pré-reserva recebida',
        self::APPROVED => 'Reserva aprovada — falta o pagamento',
        self::REFUSED => 'Pré-reserva recusada',
        self::CONFIRMED => 'Reserva confirmada',
        self::EXPIRED => 'Reserva expirada',
        self::CANCELLED => 'Reserva cancelada',
    ];

    public function __construct(private ?EmailService $email = null)
    {
        $this->email ??= new EmailService();
    }

    public function notify(SocialBooking $booking, string $type, array $extra = []): bool
    {
        try {
            $data = $this->build($booking, $type, $extra);
        } catch (\Throwable $e) {
            Log::error('Falha ao montar o e-mail da reserva social.', [
                'type' => $type,
                'social_booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return $data ? $this->email->sendScheduleMail($data) : false;
    }

    /** Conteúdo do e-mail, a partir do que está gravado. Null quando não há sócio. */
    public function build(SocialBooking $booking, string $type, array $extra = []): ?array
    {
        $booking->loadMissing(['place.group', 'items']);
        $member = $booking->member ?? Member::find($booking->member_id);

        if (! $member) {
            Log::warning('E-mail da reserva social não enviado: sócio não encontrado.', ['social_booking_id' => $booking->id]);

            return null;
        }

        $money = fn ($value) => number_format((float) $value, 2, ',', '.');
        $date = Carbon::parse($booking->event_date)->locale('pt_BR');
        $placeName = trim(($booking->place?->group?->name ?? '') . ' - ' . ($booking->place?->name ?? 'Espaço'), ' -');

        return [
            'email' => $member->email,
            'name' => $member->name,
            'type' => $type,
            'subject' => (self::TITLES[$type] ?? 'Reserva de espaço social') . ' - ' . $placeName . ', ' . $date->format('d/m/Y'),
            'title' => self::TITLES[$type] ?? 'Reserva de espaço social',
            'schedule_ids' => [$booking->schedule_id],
            'booking_id' => $booking->id,
            'place_name' => $placeName,
            'date' => $date->isoFormat('DD [de] MMMM [de] YYYY'),
            'weekday' => ucfirst($date->isoFormat('dddd')),
            'event_type' => $booking->event_type,
            'time' => $booking->timeLabel(),
            'tables_total' => $booking->tablesTotal(),
            'chairs_total' => $booking->chairsTotal(),
            'stage_label' => $booking->stageLabel(),
            'base_price' => $money($booking->base_price),
            'extra_guests' => (int) $booking->extra_guests,
            'extras_total' => $money($booking->extras_total),
            'items' => $booking->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'subtotal' => $money($item->subtotal),
            ])->all(),
            'discount' => (float) $booking->discount > 0 ? $money($booking->discount) : null,
            'total' => $money($booking->total),
            'payment_due_at' => $booking->payment_due_at?->format('d/m/Y H:i'),
            'refusal_reason' => $booking->refusal_reason,
            'cancel_reason' => $booking->cancel_reason,
            'refunded' => ! empty($extra['refunded']) ? $money($extra['refunded']) : null,
            'guest_limit' => $booking->guestLimit(),
            'rules' => $booking->place?->group?->social_rules,
            'issued_at' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }
}

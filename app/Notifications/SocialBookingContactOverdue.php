<?php

namespace App\Notifications;

use App\Models\Social\SocialBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sino do painel: o prazo de contato com o sócio venceu sem contato registrado.
 * Sai uma vez por pré-reserva (`contact_overdue_notified_at`).
 */
class SocialBookingContactOverdue extends Notification
{
    use Queueable;

    public function __construct(public SocialBooking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->booking->loadMissing('place');
        $place = $booking->place?->name ?? 'espaço social';

        return [
            'type' => 'social_booking_contact_overdue',
            'social_booking_id' => $booking->id,
            'title' => 'Contato atrasado: ' . $place,
            'message' => 'A pré-reserva para ' . $booking->event_date->format('d/m/Y')
                . ' passou do prazo de contato (' . $booking->contact_due_at?->format('d/m H:i') . ').',
            'url' => route('social-booking.show', $booking->id, false),
        ];
    }
}

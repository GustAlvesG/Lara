<?php

namespace App\Notifications;

use App\Models\Social\SocialBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sino do painel: chegou pré-reserva de espaço social pelo app, e a equipe tem
 * N dias úteis para contatar o sócio (`contact_due_at`).
 */
class SocialBookingRequested extends Notification
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
        $date = $booking->event_date->format('d/m/Y');
        $due = $booking->contact_due_at?->format('d/m H:i');

        return [
            'type' => 'social_booking_requested',
            'social_booking_id' => $booking->id,
            'title' => 'Nova pré-reserva: ' . $place,
            'message' => "Pré-reserva para {$date}." . ($due ? " Contatar o sócio até {$due}." : ''),
            'url' => route('social-booking.show', $booking->id, false),
        ];
    }
}

<?php

namespace App\Services\Social;

use App\Authorization\AccessResolver;
use App\Authorization\Permissions;
use App\Models\Social\SocialBooking;
use App\Models\User;
use App\Notifications\SocialBookingContactOverdue;
use App\Notifications\SocialBookingRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Avisos à equipe no sino do painel sobre o prazo de contato da pré-reserva.
 *
 * Recebe quem tem `reservas.sociais` por setor ou individualmente
 * (AccessResolver::holderIds — o acesso total não conta). Aviso é efeito
 * colateral: falhou, registra e segue; a reserva já está gravada.
 */
class SocialStaffNotifier
{
    public function __construct(private AccessResolver $access)
    {
    }

    public function requested(SocialBooking $booking): void
    {
        $this->send(fn () => new SocialBookingRequested($booking), $booking);
    }

    /**
     * Pré-reservas que passaram do prazo de contato sem contato registrado:
     * um aviso por pré-reserva. Roda pelo agendador (`social:contact-reminders`).
     */
    public function overdue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $bookings = SocialBooking::pendingContact()
            ->whereNotNull('contact_due_at')
            ->where('contact_due_at', '<', $now)
            ->whereNull('contact_overdue_notified_at')
            ->get();

        foreach ($bookings as $booking) {
            $this->send(fn () => new SocialBookingContactOverdue($booking), $booking);
            $booking->forceFill(['contact_overdue_notified_at' => $now])->save();
        }

        return $bookings->count();
    }

    private function send(callable $notification, SocialBooking $booking): void
    {
        try {
            User::whereIn('id', $this->access->holderIds(Permissions::RESERVAS_SOCIAIS))
                ->get()
                ->each(fn (User $user) => $user->notify($notification()));
        } catch (\Throwable $e) {
            Log::error('Falha ao avisar a equipe sobre a reserva social.', [
                'social_booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

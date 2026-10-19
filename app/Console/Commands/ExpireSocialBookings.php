<?php

namespace App\Console\Commands;

use App\Services\Social\SocialBookingService;
use Illuminate\Console\Command;

/**
 * Expira a reserva de espaço social aprovada e não paga dentro do prazo
 * (`payment_due_at`), liberando a data e avisando o sócio por e-mail.
 *
 * A pré-reserva ainda em análise não expira sozinha: a data fica presa até a
 * equipe decidir. O hold de 10 minutos das quadras não alcança a reserva
 * social (ExpirePendingSchedules a deixa de fora).
 */
class ExpireSocialBookings extends Command
{
    protected $signature = 'social:expire-bookings';

    protected $description = 'Expira reservas de espaço social aprovadas cujo prazo de pagamento venceu.';

    public function handle(SocialBookingService $bookings): int
    {
        $expired = $bookings->expireOverdue();

        if ($expired > 0) {
            $this->info("{$expired} reserva(s) social(is) expirada(s).");
        }

        return self::SUCCESS;
    }
}

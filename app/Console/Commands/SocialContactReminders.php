<?php

namespace App\Console\Commands;

use App\Services\Social\SocialStaffNotifier;
use Illuminate\Console\Command;

/**
 * Avisa a equipe (sino do painel) das pré-reservas de espaço social que
 * passaram do prazo de contato — N dias úteis, `config/social_bookings.php` —
 * sem contato registrado. Um aviso por pré-reserva.
 */
class SocialContactReminders extends Command
{
    protected $signature = 'social:contact-reminders';

    protected $description = 'Avisa a equipe das pré-reservas de espaço social com prazo de contato vencido.';

    public function handle(SocialStaffNotifier $notifier): int
    {
        $count = $notifier->overdue();

        if ($count > 0) {
            $this->info("{$count} pré-reserva(s) com contato atrasado avisada(s).");
        }

        return self::SUCCESS;
    }
}

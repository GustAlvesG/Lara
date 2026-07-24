<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class ExpirePendingSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-pending-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira (status_id=4) agendamentos pendentes (status_id=3) cujo hold ultrapassou o tempo limite, liberando o horário.';

    /**
     * Janela de tolerância do hold pendente, em minutos, antes de liberar o horário.
     */
    protected int $holdMinutes = 30;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expired = Schedule::withoutGlobalScopes()
            ->where('status_id', 3)
            ->where('created_at', '<', now()->subMinutes($this->holdMinutes))
            ->update(['status_id' => 4]);

        if ($expired > 0) {
            $this->info("{$expired} agendamento(s) pendente(s) expirado(s).");
        }
    }
}

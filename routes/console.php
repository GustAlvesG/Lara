<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('avisos:process-notifications')->everyMinute();
Schedule::command('app:expire-pending-schedules')->everyMinute();
Schedule::command('app:expire-uber-access-requests')->everyMinute();
Schedule::command('app:prune-uber-access-request-messages')->dailyAt('03:00');

// Confere no Sicoob o desfecho dos Pix em processamento e dá a baixa dos que
// finalizaram. Só CONSULTA — nunca envia —, por isso pode rodar a cada minuto.
// É o que fecha o ciclo do Job, que de propósito não tem retry.
Schedule::command('sicoob:pix-reconciliar')->everyMinute()->withoutOverlapping();

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

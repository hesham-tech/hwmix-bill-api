<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;
Schedule::command('app:master-data-cleanup')->daily();
Schedule::command('notifications:process-workflows')->daily();
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

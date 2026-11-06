<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;
Schedule::command('app:master-data-cleanup')->daily()->withoutOverlapping(60);
Schedule::command('notifications:process-workflows --limit=100 --seconds=20')->everyMinute()->withoutOverlapping(2);
// Queue workers belong to the VPS process supervisor. Shared hosting uses QUEUE_CONNECTION=sync.

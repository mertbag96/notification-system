<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:prune-idempotency')
    ->dailyAt('03:00')
    ->withoutOverlapping();

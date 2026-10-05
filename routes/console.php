<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('hotspot:import --link')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
Schedule::command('registration:purge-docs')->dailyAt('02:00');
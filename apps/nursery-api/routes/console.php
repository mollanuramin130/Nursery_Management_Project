<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:release-expired-reservations --hours=24')
    ->hourly()
    ->withoutOverlapping(55);

Schedule::command('subscriptions:process-due --limit=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

Schedule::command('marketing:process-abandoned-carts --limit=100')
    ->hourly()
    ->withoutOverlapping(55);

Schedule::command('marketing:process-post-purchase --limit=100')
    ->dailyAt('10:00')
    ->withoutOverlapping(55);

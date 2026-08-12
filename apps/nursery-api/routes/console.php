<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:release-expired-reservations --hours=24')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('subscriptions:process-due --limit=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('marketing:process-abandoned-carts --limit=100')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('marketing:process-post-purchase --limit=100')
    ->dailyAt('10:00')
    ->withoutOverlapping();

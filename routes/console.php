<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command(
    'forexfactory:scrape'
)
    ->weekdays()
    ->everyTenMinutes()
    ->between('09:00', '17:50')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('xauwire-news');

Schedule::command(
    'forexfactory:calendar'
)
    ->weekdays()
    ->dailyAt('09:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('xauwire-calendar');

Schedule::command(
    'forexfactory:session --open'
)
    ->weekdays()
    ->dailyAt('09:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('xauwire-session-open');

Schedule::command(
    'forexfactory:session --new-york'
)
    ->weekdays()
    ->dailyAt('14:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('xauwire-new-york');

Schedule::command(
    'forexfactory:session-close'
)
    ->weekdays()
    ->dailyAt('18:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('xauwire-session-close');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

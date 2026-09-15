<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('decrees:sync')
    ->timezone('Europe/Kyiv')
    ->hourly()
    ->between('9:00', '21:00')
    ->withoutOverlapping()
    ->before(function () {
        Log::info('Scheduler: decrees:sync command has started execution.');
    })
    ->after(function () {
        Log::info('Scheduler: decrees:sync command has completed execution.');
    })
    ->onFailure(function () {
        Log::error('Scheduler: decrees:sync command failed.');
    })
    ->appendOutputTo(storage_path('logs/decrees-sync.log'));

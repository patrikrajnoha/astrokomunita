<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler (produkčný crawl + logovanie do crawl_runs)
Schedule::command('events:import:tracked astropixels https://example.com --parser=table')
    ->dailyAt('02:00')
    ->withoutOverlapping();

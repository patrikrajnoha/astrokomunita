<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ------------------------------------------------------------------
// AstroBot Commands
// ------------------------------------------------------------------
Artisan::command('astrobot:ensure-user', function () {
    $this->call(\App\Console\Commands\AstroBotEnsureUser::class);
})->purpose('Ensure AstroBot user exists');

Artisan::command('astrobot:fetch {--source=nasa_news}', function () {
    $this->call(\App\Console\Commands\AstroBotFetch::class);
})->purpose('Fetch RSS items for AstroBot');

Artisan::command('astrobot:publish-scheduled', function () {
    $this->call(\App\Console\Commands\AstroBotPublishScheduled::class);
})->purpose('Publish scheduled AstroBot items');

// ------------------------------------------------------------------
// Scheduler (produkčný crawl + logovanie do crawl_runs)
// ------------------------------------------------------------------
Schedule::command('events:import:tracked astropixels https://example.com --parser=table')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('news:import-nasa --limit=20')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nasa_rss_import.log'));

// ------------------------------------------------------------------
// AstroBot Scheduler
// ------------------------------------------------------------------
Schedule::command('astrobot:fetch')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/astrobot_fetch.log'));

Schedule::command('astrobot:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/astrobot_publish_scheduled.log'));

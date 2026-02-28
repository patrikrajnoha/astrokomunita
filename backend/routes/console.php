<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('moderation:run {post_id}', function () {
    $this->call(\App\Console\Commands\RunPostModerationCommand::class, [
        'post_id' => $this->argument('post_id'),
    ]);
})->purpose('Run moderation immediately for one post (local debug).');

// ------------------------------------------------------------------
// Scheduler (production crawl + crawl_runs logging)
// ------------------------------------------------------------------
$currentYear = now()->year;
$minYear = (int) config('events.astropixels.min_year', 2021);
$maxYear = (int) config('events.astropixels.max_year', 2030);
$boundedCurrentYear = max($minYear, min($maxYear, $currentYear));
$nextYear = $boundedCurrentYear + 1 <= $maxYear ? $boundedCurrentYear + 1 : null;

Schedule::command("events:crawl-astropixels --year={$boundedCurrentYear}")
    ->dailyAt('01:30')
    ->withoutOverlapping();

if ($nextYear !== null) {
    Schedule::command("events:crawl-astropixels --year={$nextYear}")
        ->dailyAt('02:00')
        ->withoutOverlapping();
}

$weeklyYears = [$boundedCurrentYear];
if ($nextYear !== null) {
    $weeklyYears[] = $nextYear;
}

foreach (array_values(array_unique($weeklyYears)) as $yearToSync) {
    Schedule::command("events:crawl-astropixels --year={$yearToSync}")
        ->weeklyOn(1, '03:00')
        ->withoutOverlapping();
}

Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:send-event-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('notifications:send-sky-alerts')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('newsletter:send-weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();

Schedule::command('bots:run nasa_rss_breaking --context=scheduled')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('bots:run wiki_onthisday_astronomy --context=scheduled')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('bots:run nasa_apod_daily --context=scheduled')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('bots:sources:sync --quiet-summary')
    ->dailyAt('00:10')
    ->withoutOverlapping();

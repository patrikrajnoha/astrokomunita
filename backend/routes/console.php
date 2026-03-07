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

Schedule::command('events:repair-translation-artifacts --limit=300')
    ->dailyAt('03:20')
    ->withoutOverlapping();

Schedule::command('events:translation-quality-report --sample=20')
    ->dailyAt('03:10')
    ->withoutOverlapping();

Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:send-event-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('notifications:send-sky-alerts')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('notifications:prune')
    ->dailyAt('00:20')
    ->withoutOverlapping();

if (config('session.driver') === 'database') {
    Schedule::command('session:prune')
        ->dailyAt('00:25')
        ->withoutOverlapping();
}

Schedule::command('newsletter:send-weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();

Schedule::command('bots:schedules:run --limit=30')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('bots:posts:cleanup --limit=200')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('bots:sources:sync --quiet-summary')
    ->dailyAt('00:10')
    ->withoutOverlapping();

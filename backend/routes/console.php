<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AstroBotNasaSyncJob;

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

Artisan::command('astrobot:rss:refresh {--source=nasa_news}', function () {
    $this->call(\App\Console\Commands\AstroBotRssRefresh::class);
})->purpose('Refresh AstroBot RSS items and apply retention cleanup');

Artisan::command('astrobot:sync-rss', function () {
    $this->call(\App\Console\Commands\AstroBotSyncRss::class);
})->purpose('Synchronize AstroBot RSS items and clean stale records');

Artisan::command('astrobot:publish-scheduled', function () {
    $this->call(\App\Console\Commands\AstroBotPublishScheduled::class);
})->purpose('Publish scheduled AstroBot items');

Artisan::command('astrobot:cleanup-expired {--dry-run}', function () {
    $this->call(\App\Console\Commands\CleanupExpiredAstroBotPosts::class);
})->purpose('Hide expired AstroBot posts (older than 24 hours)');

Artisan::command('astrobot:purge-old-posts {--dry-run} {--hours=}', function () {
    $this->call(\App\Console\Commands\AstroBotPurgeOldPosts::class);
})->purpose('Permanently delete AstroBot posts older than 24 hours');

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

Schedule::command('news:import-nasa --limit=20')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nasa_rss_import.log'));

// ------------------------------------------------------------------
// AstroBot Scheduler
// ------------------------------------------------------------------
Schedule::job(new AstroBotNasaSyncJob())
    ->name('astrobot:nasa:sync-job')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

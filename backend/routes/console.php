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
// Scheduler (produkčný crawl + logovanie do crawl_runs)
// ------------------------------------------------------------------
Schedule::command('events:import:tracked astropixels https://example.com --parser=table')
    ->dailyAt('02:00')
    ->withoutOverlapping();

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

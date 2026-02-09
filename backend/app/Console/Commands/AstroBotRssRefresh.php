<?php

namespace App\Console\Commands;

use App\Services\AstroBotRssRefreshService;
use App\Services\RssFetchService;
use Illuminate\Console\Command;

class AstroBotRssRefresh extends Command
{
    protected $signature = 'astrobot:rss:refresh {--source=nasa_news}';
    protected $description = 'Refresh AstroBot RSS items and apply retention cleanup.';

    public function __construct(
        private readonly AstroBotRssRefreshService $refreshService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = (string) $this->option('source');
        if ($source === '') {
            $source = RssFetchService::SOURCE_NASA_NEWS;
        }

        $this->info("Refreshing AstroBot RSS source: {$source}");

        $result = $this->refreshService->refresh($source);

        $this->info(sprintf(
            'Created: %d, Skipped: %d, Errors: %d, Deleted(age): %d, Deleted(limit): %d',
            $result['created'],
            $result['skipped'],
            $result['errors'],
            $result['deleted_by_age'],
            $result['deleted_by_limit']
        ));

        return self::SUCCESS;
    }
}


<?php

namespace App\Console\Commands;

use App\Services\AstroBotSyncOrchestratorService;
use Illuminate\Console\Command;

class AstroBotSyncRss extends Command
{
    protected $signature = 'astrobot:sync-rss';
    protected $description = 'Synchronize AstroBot RSS feed with idempotent upsert and cleanup.';

    public function __construct(
        private readonly AstroBotSyncOrchestratorService $orchestrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $stats = $this->orchestrator->syncAndProcess();

        $this->info(sprintf(
            'Added: %d, Updated: %d, Published: %d, Needs review: %d, Rejected: %d, Deleted: %d, Skipped: %d, Errors: %d',
            $stats['added'],
            $stats['updated'],
            $stats['published'],
            $stats['needs_review'],
            $stats['rejected'],
            $stats['deleted'],
            $stats['skipped'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Bots\BotSourceSyncService;
use App\Services\Bots\BotScheduleSyncService;
use Illuminate\Console\Command;

class SyncBotSourcesCommand extends Command
{
    protected $signature = 'bots:sources:sync {--quiet-summary : Do not print success summary}';

    protected $description = 'Ensure default bot sources and schedules exist and are up to date.';

    public function __construct(
        private readonly BotSourceSyncService $sourceSyncService,
        private readonly BotScheduleSyncService $scheduleSyncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceResult = $this->sourceSyncService->syncDefaults();
        $scheduleResult = $this->scheduleSyncService->syncDefaults();

        if (!$this->option('quiet-summary')) {
            $this->info(sprintf(
                'Bot automation synchronized. sources(created=%d updated=%d total=%d) schedules(created=%d skipped=%d total=%d)',
                (int) $sourceResult['created'],
                (int) $sourceResult['updated'],
                (int) $sourceResult['total'],
                (int) $scheduleResult['created'],
                (int) $scheduleResult['skipped'],
                (int) $scheduleResult['total'],
            ));
        }

        return self::SUCCESS;
    }
}

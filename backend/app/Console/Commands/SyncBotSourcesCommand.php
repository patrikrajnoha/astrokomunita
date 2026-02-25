<?php

namespace App\Console\Commands;

use App\Services\Bots\BotSourceSyncService;
use Illuminate\Console\Command;

class SyncBotSourcesCommand extends Command
{
    protected $signature = 'bots:sources:sync {--quiet-summary : Do not print success summary}';

    protected $description = 'Ensure default bot_sources rows exist and are up to date.';

    public function __construct(
        private readonly BotSourceSyncService $syncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->syncService->syncDefaults();

        if (!$this->option('quiet-summary')) {
            $this->info(sprintf(
                'Bot sources synchronized. created=%d updated=%d total=%d',
                (int) $result['created'],
                (int) $result['updated'],
                (int) $result['total'],
            ));
        }

        return self::SUCCESS;
    }
}


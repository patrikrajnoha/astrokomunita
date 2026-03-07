<?php

namespace App\Console\Commands;

use App\Services\Bots\BotPostRetentionService;
use Illuminate\Console\Command;

class CleanupBotPostsCommand extends Command
{
    protected $signature = 'bots:posts:cleanup
        {--limit=200 : Max bot posts to delete in one run}
        {--force : Run cleanup even when retention is disabled}';

    protected $description = 'Delete old bot posts according to bot post retention settings.';

    public function __construct(
        private readonly BotPostRetentionService $retentionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $enabled = $this->retentionService->isEnabled();
        $limit = max(1, min(1000, (int) $this->option('limit')));

        if (!$enabled && !$force) {
            $this->info('Bot post retention cleanup skipped (disabled).');
            return self::SUCCESS;
        }

        $result = $this->retentionService->cleanupExpiredPosts($limit);

        $this->line(sprintf(
            'cutoff_at=%s retention_hours=%d matched_items=%d processed_items=%d',
            (string) ($result['cutoff_at'] ?? '-'),
            (int) ($result['retention_hours'] ?? 0),
            (int) ($result['matched_items'] ?? 0),
            (int) ($result['processed_items'] ?? 0),
        ));
        $this->line(sprintf(
            'deleted_posts=%d missing_posts=%d updated_items=%d failed_items=%d',
            (int) ($result['deleted_posts'] ?? 0),
            (int) ($result['missing_posts'] ?? 0),
            (int) ($result['updated_items'] ?? 0),
            (int) ($result['failed_items'] ?? 0),
        ));

        return self::SUCCESS;
    }
}


<?php

namespace App\Console\Commands;

use App\Models\BotSource;
use App\Services\Bots\BotRunner;
use Illuminate\Console\Command;

class RunBotSourceCommand extends Command
{
    protected $signature = 'bots:run {sourceKey} {--context=cli} {--force-manual-override}';

    protected $description = 'Run one bot source end-to-end (fetch, dedupe, publish, audit).';

    public function __construct(
        private readonly BotRunner $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceKey = strtolower(trim((string) $this->argument('sourceKey')));
        $runContext = strtolower(trim((string) $this->option('context')));
        $forceManualOverride = (bool) $this->option('force-manual-override');

        $source = BotSource::query()->where('key', $sourceKey)->first();
        if (!$source) {
            $this->error(sprintf('Bot source "%s" was not found.', $sourceKey));

            return self::FAILURE;
        }

        if (!$source->is_enabled) {
            $this->error(sprintf('Bot source "%s" is disabled.', $sourceKey));

            return self::FAILURE;
        }

        $run = $this->runner->run($source, $runContext, $forceManualOverride);
        $stats = is_array($run->stats) ? $run->stats : [];

        $this->info(sprintf('Run #%d finished with status: %s', $run->id, (string) ($run->status?->value ?? $run->status)));
        $this->line(sprintf(
            'fetched=%d new=%d dupes=%d published=%d skipped=%d failed=%d',
            (int) ($stats['fetched_count'] ?? 0),
            (int) ($stats['new_count'] ?? 0),
            (int) ($stats['dupes_count'] ?? 0),
            (int) ($stats['published_count'] ?? 0),
            (int) ($stats['skipped_count'] ?? 0),
            (int) ($stats['failed_count'] ?? 0),
        ));

        if (!empty($run->error_text)) {
            $this->warn((string) $run->error_text);
        }

        if ((int) ($stats['run_locked'] ?? 0) === 1) {
            $this->warn(sprintf(
                'Run skipped due to lock: %s',
                (string) ($stats['lock_key'] ?? 'unknown_lock')
            ));
        }

        $status = $run->status?->value ?? (string) $run->status;
        if (in_array($status, ['failed', 'partial'], true)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

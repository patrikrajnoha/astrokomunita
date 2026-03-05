<?php

namespace App\Console\Commands;

use App\Services\Bots\BotScheduleRunnerService;
use Illuminate\Console\Command;

class RunBotSchedulesCommand extends Command
{
    protected $signature = 'bots:schedules:run {--limit=20}';

    protected $description = 'Run due bot schedules and update their next execution time.';

    public function __construct(
        private readonly BotScheduleRunnerService $scheduleRunnerService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 20;
        }

        $stats = $this->scheduleRunnerService->runDueSchedules($limit);

        $this->info(sprintf(
            'processed=%d success=%d skipped=%d failed=%d',
            (int) ($stats['processed_count'] ?? 0),
            (int) ($stats['success_count'] ?? 0),
            (int) ($stats['skipped_count'] ?? 0),
            (int) ($stats['failed_count'] ?? 0),
        ));

        return ((int) ($stats['failed_count'] ?? 0)) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}


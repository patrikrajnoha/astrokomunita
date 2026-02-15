<?php

namespace App\Console\Commands;

use App\Services\Translation\EventTranslationBackfillService;
use Illuminate\Console\Command;

class BackfillEventTranslationsCommand extends Command
{
    protected $signature = 'events:backfill-translations
                            {--limit=0 : Max approved candidates to process (0 = all)}
                            {--dry-run : Do not persist changes}
                            {--force : Retranslate even if translated fields already exist}';

    protected $description = 'Backfill Slovak translations for approved event candidates and sync to published events.';

    public function __construct(
        private readonly EventTranslationBackfillService $backfillService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->backfillService->run(
            limit: max(0, (int) $this->option('limit')),
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force')
        );

        $total = (int) $summary['total_candidates'];

        if ($total === 0) {
            $this->info('No candidates require backfill.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} approved candidates...");

        foreach ($summary['failures'] as $failure) {
            $this->warn(sprintf(
                'Candidate %d: translation failed (%s)',
                (int) $failure['candidate_id'],
                (string) $failure['error_code']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Backfill summary processed=%d translated=%d failed=%d events_updated=%d dry_run=%s',
            (int) $summary['processed'],
            (int) $summary['translated'],
            (int) $summary['failed'],
            (int) $summary['events_updated'],
            ((bool) $summary['dry_run']) ? 'yes' : 'no'
        ));

        return ((int) $summary['failed']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}

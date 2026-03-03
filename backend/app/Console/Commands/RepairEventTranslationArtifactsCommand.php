<?php

namespace App\Console\Commands;

use App\Services\Translation\EventTranslationArtifactDetector;
use App\Services\Translation\EventTranslationBackfillService;
use Illuminate\Console\Command;

class RepairEventTranslationArtifactsCommand extends Command
{
    protected $signature = 'events:repair-translation-artifacts
                            {--limit=0 : Max suspicious approved candidates to process (0 = all)}
                            {--dry-run : Do not persist changes}';

    protected $description = 'Detect suspicious mixed-language event translations and re-run force backfill for affected candidates.';

    public function __construct(
        private readonly EventTranslationBackfillService $backfillService,
        private readonly EventTranslationArtifactDetector $artifactDetector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $candidateIds = $this->artifactDetector->suspiciousCandidateIds($limit);

        if ($candidateIds === []) {
            $this->info('No suspicious translation artifacts detected.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Suspicious approved candidates found: %d', count($candidateIds)));

        $summary = $this->backfillService->run(
            limit: 0,
            dryRun: $dryRun,
            force: true,
            candidateIds: $candidateIds
        );

        foreach ($summary['failures'] as $failure) {
            $this->warn(sprintf(
                'Candidate %d: translation failed (%s)',
                (int) $failure['candidate_id'],
                (string) $failure['error_code']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Repair summary processed=%d translated=%d failed=%d events_updated=%d dry_run=%s',
            (int) $summary['processed'],
            (int) $summary['translated'],
            (int) $summary['failed'],
            (int) $summary['events_updated'],
            ((bool) $summary['dry_run']) ? 'yes' : 'no'
        ));

        return ((int) $summary['failed']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}

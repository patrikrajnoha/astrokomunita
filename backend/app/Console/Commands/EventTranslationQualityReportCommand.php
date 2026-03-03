<?php

namespace App\Console\Commands;

use App\Services\Translation\EventTranslationArtifactDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EventTranslationQualityReportCommand extends Command
{
    protected $signature = 'events:translation-quality-report
                            {--sample=10 : Max suspicious rows to print}
                            {--fail-on-findings : Return non-zero when suspicious rows are found}';

    protected $description = 'Report suspicious mixed-language event translations without changing data.';

    public function __construct(
        private readonly EventTranslationArtifactDetector $artifactDetector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sampleLimit = max(1, (int) $this->option('sample'));
        $count = $this->artifactDetector->suspiciousCount();

        if ($count === 0) {
            $this->info('Translation quality report: no suspicious artifacts found.');
            return self::SUCCESS;
        }

        $this->warn(sprintf('Translation quality report: suspicious approved candidates found: %d', $count));
        $samples = $this->artifactDetector->suspiciousSamples($sampleLimit);

        if ($samples !== []) {
            $rows = array_map(static function (array $sample): array {
                return [
                    (string) $sample['candidate_id'],
                    $sample['event_id'] !== null ? (string) $sample['event_id'] : '-',
                    Str::limit($sample['source_title'] !== '' ? $sample['source_title'] : '-', 80),
                    Str::limit($sample['translated_title'] !== '' ? $sample['translated_title'] : '-', 80),
                    Str::limit($sample['event_title'] !== '' ? $sample['event_title'] : '-', 80),
                ];
            }, $samples);

            $this->newLine();
            $this->table(
                ['Candidate', 'Event', 'Source title', 'Translated title', 'Event title'],
                $rows
            );
        }

        if ((bool) $this->option('fail-on-findings')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

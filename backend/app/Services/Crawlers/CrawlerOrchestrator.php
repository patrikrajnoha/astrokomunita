<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Models\CrawlRun;
use App\Models\EventSource as EventSourceModel;
use App\Services\EventImport\EventImportService;
use Carbon\CarbonImmutable;
use Throwable;

class CrawlerOrchestrator
{
    public function __construct(
        private readonly EventImportService $importService,
    ) {
    }

    public function run(CrawlerInterface $crawler, CrawlContext $context): CrawlRun
    {
        $sourceEnum = $this->resolveSource($crawler);
        $sourceModel = EventSourceModel::query()->firstOrCreate(
            ['key' => $sourceEnum->value],
            [
                'name' => $sourceEnum->label(),
                'base_url' => null,
                'is_enabled' => true,
            ]
        );

        $run = CrawlRun::query()->create([
            'event_source_id' => $sourceModel->id,
            'source_name' => $sourceEnum->value,
            'source_url' => $this->guessSourceUrl($context->year),
            'year' => $context->year,
            'started_at' => CarbonImmutable::now('UTC'),
            'status' => 'running',
        ]);

        try {
            $batch = $crawler->fetchCandidates($context);
            $result = $this->importService->importFromCandidateItems(
                sourceName: $batch->source->value,
                sourceUrl: $this->guessSourceUrl($context->year),
                items: $batch->items,
                eventSourceId: $sourceModel->id,
                dryRun: $context->dryRun,
            );

            $run->update([
                'source_url' => $this->guessSourceUrl($context->year),
                'finished_at' => CarbonImmutable::now('UTC'),
                'status' => 'success',
                'fetched_bytes' => $batch->fetchedBytes,
                'fetched_count' => $result->total,
                'parsed_items' => $result->total,
                'inserted_candidates' => $result->imported,
                'created_candidates_count' => $result->imported,
                'duplicates' => $result->duplicates,
                'skipped_duplicates_count' => $result->duplicates,
                'errors_count' => 0,
                'error_log' => null,
                'error_summary' => null,
            ]);
        } catch (Throwable $e) {
            $summary = mb_substr($e->getMessage(), 0, 2000);

            $run->update([
                'finished_at' => CarbonImmutable::now('UTC'),
                'status' => 'failed',
                'errors_count' => 1,
                'error_log' => mb_substr((string) $e, 0, 12000),
                'error_summary' => $summary,
            ]);
        }

        return $run->fresh();
    }

    private function resolveSource(CrawlerInterface $crawler): EventSource
    {
        if ($crawler instanceof AstropixelsCrawlerService) {
            return EventSource::ASTROPIXELS;
        }

        return EventSource::ASTROPIXELS;
    }

    private function guessSourceUrl(int $year): string
    {
        $pattern = (string) config('events.astropixels.base_url_pattern', 'https://astropixels.com/almanac/almanac21/almanac%dcet.html');
        return sprintf($pattern, $year);
    }
}

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
        $sourceEnum = $crawler->source();
        $sourceUrl = $this->resolveSourceUrl($sourceEnum, $context->year);
        $sourceModel = EventSourceModel::query()->firstOrCreate(
            ['key' => $sourceEnum->value],
            [
                'name' => $sourceEnum->label(),
                'base_url' => $sourceUrl,
                'is_enabled' => true,
            ]
        );

        $startedAt = CarbonImmutable::now('UTC');
        $run = CrawlRun::query()->create([
            'event_source_id' => $sourceModel->id,
            'source_name' => $sourceEnum->value,
            'source_url' => $sourceUrl,
            'source_year' => $context->year,
            'year' => $context->year,
            'started_at' => $startedAt,
            'headers_used' => false,
            'status' => 'running',
        ]);

        if (! (bool) $sourceModel->is_enabled) {
            $finishedAt = CarbonImmutable::now('UTC');
            $run->update([
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'status' => 'skipped',
                'errors_count' => 0,
                'error_code' => 'source_disabled',
                'error_summary' => sprintf('Source "%s" is disabled.', $sourceEnum->value),
            ]);

            return $run->fresh();
        }

        try {
            $batch = $crawler->fetchCandidates($context);
            $result = $this->importService->importFromCandidateItems(
                sourceName: $batch->source->value,
                sourceUrl: $batch->sourceUrl ?? $sourceUrl,
                items: $batch->items,
                eventSourceId: $sourceModel->id,
                dryRun: $context->dryRun,
            );
            $finishedAt = CarbonImmutable::now('UTC');

            $run->update([
                'source_url' => $batch->sourceUrl ?? $sourceUrl,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'status' => 'success',
                'headers_used' => $batch->headersUsed,
                'fetched_bytes' => $batch->fetchedBytes,
                'fetched_count' => $result->total,
                'parsed_items' => $result->total,
                'inserted_candidates' => $result->imported,
                'created_candidates_count' => $result->imported,
                'updated_candidates_count' => $result->updated,
                'duplicates' => $result->duplicates,
                'skipped_duplicates_count' => $result->duplicates,
                'errors_count' => 0,
                'diagnostics' => $this->encodeDiagnostics($batch->diagnostics),
                'error_code' => null,
                'error_log' => null,
                'error_summary' => null,
            ]);
        } catch (Throwable $e) {
            $finishedAt = CarbonImmutable::now('UTC');
            $summary = mb_substr($e->getMessage(), 0, 2000);

            $run->update([
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'status' => 'failed',
                'errors_count' => 1,
                'error_code' => $this->resolveErrorCode($e),
                'error_log' => $this->truncateText((string) $e, 12000),
                'error_summary' => $summary,
            ]);
        }

        return $run->fresh();
    }

    private function resolveSourceUrl(EventSource $source, int $year): string
    {
        return match ($source) {
            EventSource::ASTROPIXELS => sprintf(
                (string) config('events.astropixels.base_url_pattern', 'https://astropixels.com/almanac/almanac21/almanac%dcet.html'),
                $year
            ),
            EventSource::NASA => (string) config('bots.nasa_rss_url', 'https://www.nasa.gov/rss/dyn/breaking_news.rss'),
            EventSource::NASA_WATCH_THE_SKIES => (string) config('events.nasa_watch_the_skies.url', 'https://science.nasa.gov/skywatching/'),
            EventSource::IMO => (string) config('events.imo.url', 'https://www.imo.net/resources/calendar/'),
        };
    }

    private function encodeDiagnostics(array $diagnostics): ?string
    {
        if ($diagnostics === []) {
            return null;
        }

        $prepared = array_slice(array_values(array_filter(array_map(
            fn ($line) => is_string($line) ? $this->truncateText($line, 240) : null,
            $diagnostics
        ))), 0, 60);

        if ($prepared === []) {
            return null;
        }

        $json = json_encode($prepared, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $this->truncateText(implode(' | ', $prepared), 2000);
        }

        return $this->truncateText($json, 6000);
    }

    private function resolveErrorCode(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'ASTROPIXELS_HTTP_ERROR')) {
            return 'astropixels_http_error';
        }
        if (str_contains($message, 'ASTROPIXELS_PARSE_ERROR')) {
            return 'astropixels_parse_error';
        }
        if (str_contains($message, 'IMO_HTTP_ERROR')) {
            return 'imo_http_error';
        }
        if (str_contains($message, 'IMO_PARSE_ERROR')) {
            return 'imo_parse_error';
        }
        if (str_contains($message, 'SSL')) {
            return 'crawler_ssl_error';
        }

        return 'crawler_runtime_error';
    }

    private function truncateText(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}

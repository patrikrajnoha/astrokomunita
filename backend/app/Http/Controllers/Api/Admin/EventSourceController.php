<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\AstroBotSyncInProgressException;
use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\EventSource;
use App\Services\AstroBotNasaService;
use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use App\Services\Crawlers\CrawlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSourceController extends Controller
{
    public function __construct(
        private readonly CrawlerRegistry $crawlerRegistry,
        private readonly CrawlerOrchestrator $orchestrator,
        private readonly AstroBotNasaService $astroBotNasaService,
    ) {
    }

    public function index(): JsonResponse
    {
        $sources = EventSource::query()
            ->orderBy('key')
            ->get()
            ->map(fn (EventSource $source): array => [
                'id' => $source->id,
                'key' => $source->key,
                'name' => $source->name,
                'base_url' => $source->base_url,
                'is_enabled' => (bool) $source->is_enabled,
                'manual_run_supported' => $this->crawlerRegistry->forSourceKey($source->key) !== null || $source->key === 'nasa',
            ])
            ->values();

        return response()->json([
            'data' => $sources,
        ]);
    }

    public function update(Request $request, EventSource $eventSource): JsonResponse
    {
        $payload = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $eventSource->update([
            'is_enabled' => (bool) $payload['is_enabled'],
        ]);

        return response()->json([
            'id' => $eventSource->id,
            'key' => $eventSource->key,
            'is_enabled' => (bool) $eventSource->is_enabled,
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_keys' => ['required', 'array', 'min:1'],
            'source_keys.*' => ['string', 'distinct'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $year = (int) ($payload['year'] ?? now()->year);
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $requestedKeys = array_values(array_unique(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            (array) $payload['source_keys']
        )));

        $sources = EventSource::query()
            ->whereIn('key', $requestedKeys)
            ->get()
            ->keyBy('key');

        $results = [];

        foreach ($requestedKeys as $key) {
            $source = $sources->get($key);
            if (! $source) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'missing',
                    'message' => 'Unknown event source.',
                ];
                continue;
            }

            if (! (bool) $source->is_enabled) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'skipped',
                    'message' => 'Source is disabled.',
                ];
                continue;
            }

            if ($key === 'nasa') {
                $results[] = $this->runNasaSource($source);
                continue;
            }

            $crawler = $this->crawlerRegistry->forSourceKey($key);
            if (! $crawler) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'unsupported',
                    'message' => 'Manual run is not supported for this source.',
                ];
                continue;
            }

            $run = $this->orchestrator->run($crawler, new CrawlContext(
                year: $year,
                timezone: $timezone,
                dryRun: $dryRun,
            ));

            $results[] = [
                'source_key' => $key,
                'status' => (string) $run->status,
                'crawl_run_id' => $run->id,
                'fetched_count' => (int) $run->fetched_count,
                'created_candidates_count' => (int) $run->created_candidates_count,
                'updated_candidates_count' => (int) $run->updated_candidates_count,
                'errors_count' => (int) $run->errors_count,
                'message' => $run->error_summary,
            ];
        }

        return response()->json([
            'status' => 'ok',
            'year' => $year,
            'dry_run' => $dryRun,
            'results' => $results,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function runNasaSource(EventSource $source): array
    {
        try {
            $summary = $this->astroBotNasaService->syncWithLock('admin_event_source_run');
        } catch (AstroBotSyncInProgressException) {
            return [
                'source_key' => $source->key,
                'status' => 'skipped',
                'message' => 'NASA sync is already running.',
            ];
        }

        $startedAt = CarbonImmutable::parse((string) ($summary['started_at'] ?? now()->toIso8601String()), 'UTC');
        $finishedAt = CarbonImmutable::parse((string) ($summary['finished_at'] ?? now()->toIso8601String()), 'UTC');
        $status = ((int) ($summary['errors'] ?? 0)) > 0 ? 'failed' : 'success';

        $run = CrawlRun::query()->create([
            'event_source_id' => $source->id,
            'source_name' => $source->key,
            'source_url' => (string) config('astrobot.nasa_rss_url'),
            'source_year' => (int) $startedAt->year,
            'year' => (int) $startedAt->year,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => (int) ($summary['duration_ms'] ?? 0),
            'status' => $status,
            'headers_used' => true,
            'fetched_count' => (int) ($summary['new'] ?? 0),
            'parsed_items' => (int) ($summary['new'] ?? 0),
            'created_candidates_count' => 0,
            'updated_candidates_count' => 0,
            'skipped_duplicates_count' => 0,
            'errors_count' => (int) ($summary['errors'] ?? 0),
            'error_summary' => $summary['error'] ?? null,
            'error_code' => $status === 'failed' ? 'nasa_sync_failed' : null,
        ]);

        return [
            'source_key' => $source->key,
            'status' => $status,
            'crawl_run_id' => $run->id,
            'message' => $summary['error'] ?? null,
        ];
    }
}

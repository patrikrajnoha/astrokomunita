<?php

namespace App\Http\Controllers\Api\Admin;

use Database\Seeders\EventSourceSeeder;
use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventSource;
use App\Services\Crawlers\Astropixels\AstropixelsYearCatalogService;
use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use App\Services\Crawlers\CrawlerRegistry;
use App\Services\Translation\EventTranslationArtifactDetector;
use App\Services\Translation\EventTranslationBackfillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventSourceController extends Controller
{
    private const DISABLED_SOURCE_KEYS = ['go_astronomy'];

    public function __construct(
        private readonly CrawlerRegistry $crawlerRegistry,
        private readonly CrawlerOrchestrator $orchestrator,
        private readonly AstropixelsYearCatalogService $astropixelsYearCatalogService,
        private readonly EventTranslationArtifactDetector $artifactDetector,
        private readonly EventTranslationBackfillService $translationBackfillService,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->ensureDefaultSources();

        $astropixelsCatalog = null;

        $sources = EventSource::query()
            ->whereNotIn('key', self::DISABLED_SOURCE_KEYS)
            ->orderBy('key')
            ->get()
            ->map(function (EventSource $source) use (&$astropixelsCatalog): array {
                return [
                    'id' => $source->id,
                    'key' => $source->key,
                    'name' => $source->name,
                    'base_url' => $source->base_url,
                    'is_enabled' => (bool) $source->is_enabled,
                    'manual_run_supported' => $this->crawlerRegistry->forSourceKey($source->key) !== null,
                    'year_catalog' => $this->resolveSourceYearCatalog((string) $source->key, $astropixelsCatalog),
                ];
            })
            ->values();

        return response()->json([
            'data' => $sources,
        ]);
    }

    public function update(Request $request, EventSource $eventSource): JsonResponse
    {
        if (in_array($eventSource->key, self::DISABLED_SOURCE_KEYS, true)) {
            abort(404);
        }

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
        $this->ensureDefaultSources();

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $payload = $request->validate([
            'source_keys' => ['required', 'array', 'min:1'],
            'source_keys.*' => ['string', 'distinct'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $year = (int) ($payload['year'] ?? now((string) config('events.timezone', 'Europe/Bratislava'))->year);
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $requestedKeys = array_values(array_unique(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            (array) $payload['source_keys']
        )));

        $blockedKeys = array_values(array_filter(
            $requestedKeys,
            fn (string $key): bool => in_array($key, self::DISABLED_SOURCE_KEYS, true)
        ));

        if ($blockedKeys !== []) {
            return response()->json([
                'message' => 'Jeden alebo viac zdrojov nie je v tomto prostredi dostupnych.',
                'errors' => [
                    'source_keys' => [
                        sprintf('Source key(s) not allowed: %s', implode(', ', $blockedKeys)),
                    ],
                ],
            ], 422);
        }

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
                    'message' => 'Neznamy zdroj udalosti.',
                ];
                continue;
            }

            if (! (bool) $source->is_enabled) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'skipped',
                    'message' => 'Zdroj je vypnuty.',
                ];
                continue;
            }

            $crawler = $this->crawlerRegistry->forSourceKey($key);
            if (! $crawler) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'unsupported',
                    'message' => 'Manualny beh nie je pre tento zdroj podporovany.',
                ];
                continue;
            }

            if ($key === 'astropixels') {
                $availability = $this->astropixelsYearCatalogService->isYearAvailable($year);
                if ($availability === false) {
                    $snapshot = $this->astropixelsYearCatalogService->snapshot();
                    $catalogYears = array_map('intval', (array) ($snapshot['available_years'] ?? []));
                    $rangeHint = $catalogYears !== []
                        ? sprintf(
                            ' Dostupny rozsah v katalogu: %d-%d.',
                            min($catalogYears),
                            max($catalogYears)
                        )
                        : '';

                    $results[] = [
                        'source_key' => $key,
                        'status' => 'skipped',
                        'message' => sprintf(
                            'AstroPixels almanac pre rok %d este nie je publikovany.%s',
                            $year,
                            $rangeHint
                        ),
                    ];
                    continue;
                }
            }

            $timezone = $this->resolveSourceTimezone($key);
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

    private function resolveSourceTimezone(string $sourceKey): string
    {
        return (string) config(
            "events.source_timezones.{$sourceKey}",
            config('events.source_timezones.default', config('events.source_timezone', 'Europe/Bratislava'))
        );
    }

    public function purge(Request $request): JsonResponse
    {
        $this->ensureDefaultSources();

        $payload = $request->validate([
            'source_keys' => ['nullable', 'array'],
            'source_keys.*' => ['string', 'distinct'],
            'dry_run' => ['sometimes', 'boolean'],
            'confirm' => ['required', 'string', 'in:delete_crawled_events'],
        ]);

        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $requestedKeys = array_values(array_unique(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            (array) ($payload['source_keys'] ?? [])
        )));

        $availableKeys = EventSource::query()
            ->orderBy('key')
            ->get()
            ->filter(fn (EventSource $source): bool => $this->crawlerRegistry->forSourceKey($source->key) !== null)
            ->map(fn (EventSource $source): string => (string) $source->key)
            ->values()
            ->all();

        $targetKeys = $requestedKeys === []
            ? $availableKeys
            : array_values(array_intersect($requestedKeys, $availableKeys));

        if ($targetKeys === []) {
            return response()->json([
                'status' => 'noop',
                'dry_run' => $dryRun,
                'source_keys' => [],
                'message' => 'Neboli vybrane ziadne kluce crawl zdrojov.',
            ]);
        }

        $sourceIds = EventSource::query()
            ->whereIn('key', $targetKeys)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $eventQuery = Event::query()->whereIn('source_name', $targetKeys);
        $candidateQuery = EventCandidate::query()
            ->whereIn('source_name', $targetKeys)
            ->orWhereIn('event_source_id', $sourceIds);
        $crawlRunQuery = CrawlRun::query()
            ->whereIn('source_name', $targetKeys)
            ->orWhereIn('event_source_id', $sourceIds);

        $counts = [
            'events' => (clone $eventQuery)->count(),
            'event_candidates' => (clone $candidateQuery)->count(),
            'crawl_runs' => (clone $crawlRunQuery)->count(),
        ];

        if (! $dryRun) {
            DB::transaction(function () use ($eventQuery, $candidateQuery, $crawlRunQuery): void {
                $crawlRunQuery->delete();
                $candidateQuery->delete();
                $eventQuery->delete();
            });
        }

        return response()->json([
            'status' => $dryRun ? 'dry_run' : 'ok',
            'dry_run' => $dryRun,
            'source_keys' => $targetKeys,
            'deleted' => $counts,
            'confirm_token' => 'delete_crawled_events',
        ]);
    }

    public function translationArtifactsReport(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sample' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sampleLimit = max(1, (int) ($payload['sample'] ?? 20));
        $suspiciousCount = $this->artifactDetector->suspiciousCount();
        $samples = $this->artifactDetector->suspiciousSamples($sampleLimit);

        return response()->json([
            'status' => 'ok',
            'summary' => [
                'suspicious_candidates' => $suspiciousCount,
                'sample_limit' => $sampleLimit,
                'sample_count' => count($samples),
                'checked_at' => now()->toISOString(),
            ],
            'samples' => $samples,
        ]);
    }

    public function translationArtifactsRepair(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dry_run' => ['sometimes', 'boolean'],
            'sample' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = max(1, (int) ($payload['limit'] ?? 300));
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $sampleLimit = max(1, (int) ($payload['sample'] ?? 20));

        $candidateIds = $this->artifactDetector->suspiciousCandidateIds($limit);
        $detectedCount = count($candidateIds);

        if ($detectedCount === 0) {
            return response()->json([
                'status' => 'noop',
                'dry_run' => $dryRun,
                'detected_count' => 0,
                'summary' => [
                    'processed' => 0,
                    'translated' => 0,
                    'failed' => 0,
                    'events_updated' => 0,
                ],
                'remaining_suspicious' => $this->artifactDetector->suspiciousCount(),
                'samples' => [],
            ]);
        }

        $repairSummary = $this->translationBackfillService->run(
            limit: 0,
            dryRun: $dryRun,
            force: true,
            candidateIds: $candidateIds
        );

        return response()->json([
            'status' => $dryRun ? 'dry_run' : 'ok',
            'dry_run' => $dryRun,
            'detected_count' => $detectedCount,
            'summary' => [
                'processed' => (int) $repairSummary['processed'],
                'translated' => (int) $repairSummary['translated'],
                'failed' => (int) $repairSummary['failed'],
                'events_updated' => (int) $repairSummary['events_updated'],
            ],
            'remaining_suspicious' => $this->artifactDetector->suspiciousCount(),
            'samples' => $this->artifactDetector->suspiciousSamples($sampleLimit),
        ]);
    }

    private function ensureDefaultSources(): void
    {
        if (EventSource::query()->exists()) {
            return;
        }

        try {
            app(EventSourceSeeder::class)->run();
        } catch (\Throwable $error) {
            Log::warning('Failed to auto-seed event sources.', [
                'message' => $error->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed>|null $astropixelsCatalogCache
     * @return array<string,mixed>|null
     */
    private function resolveSourceYearCatalog(string $sourceKey, ?array &$astropixelsCatalogCache): ?array
    {
        if (strtolower(trim($sourceKey)) !== 'astropixels') {
            return null;
        }

        if ($astropixelsCatalogCache === null) {
            $astropixelsCatalogCache = $this->astropixelsYearCatalogService->snapshot();
        }

        $availableYears = array_map('intval', (array) ($astropixelsCatalogCache['available_years'] ?? []));
        sort($availableYears);

        $fallbackMin = (int) config('events.astropixels.min_year', 2021);
        $fallbackMax = (int) config('events.astropixels.max_year', 2100);
        $resolvedMin = $availableYears !== [] ? min($availableYears) : $fallbackMin;
        $resolvedMax = $availableYears !== [] ? max($availableYears) : $fallbackMax;

        return [
            'status' => (string) ($astropixelsCatalogCache['status'] ?? 'unknown'),
            'checked_at' => (string) ($astropixelsCatalogCache['checked_at'] ?? now()->toISOString()),
            'source_url' => (string) ($astropixelsCatalogCache['source_url'] ?? ''),
            'error' => $astropixelsCatalogCache['error'] ?? null,
            'min_year' => $resolvedMin,
            'max_year' => $resolvedMax,
            'available_years' => $availableYears,
        ];
    }
}


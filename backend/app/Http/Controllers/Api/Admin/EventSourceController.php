<?php

namespace App\Http\Controllers\Api\Admin;

use Database\Seeders\EventSourceSeeder;
use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventSource;
use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use App\Services\Crawlers\CrawlerRegistry;
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
    ) {
    }

    public function index(): JsonResponse
    {
        $this->ensureDefaultSources();

        $sources = EventSource::query()
            ->whereNotIn('key', self::DISABLED_SOURCE_KEYS)
            ->orderBy('key')
            ->get()
            ->map(fn (EventSource $source): array => [
                'id' => $source->id,
                'key' => $source->key,
                'name' => $source->name,
                'base_url' => $source->base_url,
                'is_enabled' => (bool) $source->is_enabled,
                'manual_run_supported' => $this->crawlerRegistry->forSourceKey($source->key) !== null,
            ])
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
                'message' => 'One or more sources are not available in this environment.',
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

            $crawler = $this->crawlerRegistry->forSourceKey($key);
            if (! $crawler) {
                $results[] = [
                    'source_key' => $key,
                    'status' => 'unsupported',
                    'message' => 'Manual run is not supported for this source.',
                ];
                continue;
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
                'message' => 'No crawl-capable source keys selected.',
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
}

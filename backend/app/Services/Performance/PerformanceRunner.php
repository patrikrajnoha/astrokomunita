<?php

namespace App\Services\Performance;

use App\Models\BotSource;
use App\Models\EventCandidate;
use App\Models\PerformanceLog;
use App\Models\User;
use App\Services\Bots\BotRunner;
use App\Services\Crawlers\CandidateItem;
use App\Services\EventImport\EventImportService;
use App\Services\Events\CanonicalKeyService;
use App\Services\Events\EventCandidatePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PerformanceRunner
{
    public function __construct(
        private readonly HttpKernel $httpKernel,
        private readonly EventImportService $eventImportService,
        private readonly EventCandidatePublisher $eventCandidatePublisher,
        private readonly CanonicalKeyService $canonicalKeyService,
        private readonly BotRunner $botRunner,
    ) {
    }

    public function runAll(): PerformanceRunResult
    {
        return $this->runAllWithOptions();
    }

    public function runAllWithOptions(
        int $sampleSize = 200,
        ?int $createdBy = null,
        string $mode = 'normal',
        string $botSourceKey = 'nasa_rss_breaking',
    ): PerformanceRunResult {
        $eventsMetric = $this->runEventsListBenchmark($sampleSize, $createdBy, $mode);
        $canonicalMetric = $this->runCanonicalMatchingBenchmark(min($sampleSize, 500), $createdBy);
        $botMetric = $this->runBotImportBenchmark($botSourceKey, [
            'iterations' => 1,
            'created_by' => $createdBy,
        ]);

        return new PerformanceRunResult([
            'events_list' => $eventsMetric,
            'canonical' => $canonicalMetric,
            'bot' => $botMetric,
        ]);
    }

    public function runEventsListBenchmark(int $n = 200, ?int $createdBy = null, string $mode = 'normal'): PerformanceMetric
    {
        $sampleSize = $this->normalizeSampleSize($n);

        // Warm-up requests are intentionally excluded from measured samples.
        for ($i = 0; $i < 5; $i++) {
            $this->dispatchEventsIndexRequest($mode);
        }

        $durations = [];
        $queryCounts = [];

        for ($i = 0; $i < $sampleSize; $i++) {
            [$durationMs, $queryCount] = $this->measureWithQueryCount(function () use ($mode): void {
                $this->dispatchEventsIndexRequest($mode);
            });

            $durations[] = $durationMs;
            $queryCounts[] = $queryCount;
        }

        return $this->persistMetric(
            key: 'events_list_' . $sampleSize,
            sampleSize: $sampleSize,
            durationsMs: $durations,
            queryCounts: $queryCounts,
            payload: [
                'mode' => $mode,
                'warmup_requests' => 5,
                'endpoint' => '/api/events?per_page=20&year=' . now()->year,
                'no_cache_supported' => false,
                'notes' => $mode === 'no_cache'
                    ? 'No explicit no-cache switch is wired for this endpoint in current implementation.'
                    : null,
            ],
            createdBy: $createdBy,
        );
    }

    public function runCanonicalMatchingBenchmark(int $n = 100, ?int $createdBy = null): PerformanceMetric
    {
        $sampleSize = $this->normalizeSampleSize($n);
        $durations = [];
        $queryCounts = [];
        $startedAt = CarbonImmutable::now('UTC');
        $reviewer = null;

        $originalTranslationEnabled = (bool) config('translation.events.enabled', true);
        config()->set('translation.events.enabled', false);

        DB::beginTransaction();

        try {
            $reviewer = User::query()->create([
                'name' => 'Bench Admin',
                'username' => 'bench_admin_' . substr(sha1((string) microtime(true)), 0, 8),
                'email' => 'bench-admin+' . substr(sha1((string) microtime(true)), 0, 8) . '@example.test',
                'password' => bcrypt('benchmark-password'),
                'role' => 'admin',
                'is_admin' => true,
                'is_active' => true,
            ]);

            for ($i = 0; $i < $sampleSize; $i++) {
                $title = 'Benchmark Candidate ' . $i;
                $startAt = $startedAt->addMinutes($i);
                $canonicalKey = $this->canonicalKeyService->make('meteor shower', $startAt, $title);

                $item = new CandidateItem(
                    title: $title,
                    startsAtUtc: $startAt,
                    endsAtUtc: null,
                    description: 'Benchmark canonical matching candidate.',
                    sourceUrl: 'https://bench.local/canonical',
                    externalId: 'bench-canonical-' . $i . '-' . substr(sha1((string) microtime(true)), 0, 8),
                    rawPayload: ['bench' => true, 'index' => $i],
                    eventType: 'meteor_shower',
                    canonicalKey: $canonicalKey,
                    confidenceScore: null,
                    matchedSources: ['bench_source'],
                );

                $this->eventImportService->importFromCandidateItems(
                    sourceName: 'bench_source',
                    sourceUrl: 'https://bench.local/canonical',
                    items: [$item],
                    eventSourceId: null,
                    dryRun: false
                );

                $candidate = EventCandidate::query()->latest('id')->firstOrFail();
                [$durationMs, $queryCount] = $this->measureWithQueryCount(function () use ($candidate, $reviewer): void {
                    $this->eventCandidatePublisher->approve($candidate, (int) $reviewer->id);
                });

                $durations[] = $durationMs;
                $queryCounts[] = $queryCount;
            }

            DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        } finally {
            config()->set('translation.events.enabled', $originalTranslationEnabled);
        }

        return $this->persistMetric(
            key: 'canonical_publish_' . $sampleSize,
            sampleSize: $sampleSize,
            durationsMs: $durations,
            queryCounts: $queryCounts,
            payload: [
                'source_name' => 'bench_source',
                'rolled_back' => true,
                'notes' => 'Candidates + events are created in transaction and rolled back to avoid data spam.',
            ],
            createdBy: $createdBy,
        );
    }

    /**
     * @param array<string,mixed> $opts
     */
    public function runBotImportBenchmark(string $sourceKey, array $opts = []): PerformanceMetric
    {
        $iterations = max(1, min(5, (int) ($opts['iterations'] ?? 1)));
        $createdBy = isset($opts['created_by']) ? (int) $opts['created_by'] : null;
        $normalizedSourceKey = strtolower(trim($sourceKey));
        $source = BotSource::query()->where('key', $normalizedSourceKey)->first();
        if (!$source) {
            throw new RuntimeException(sprintf('Bot source "%s" was not found.', $normalizedSourceKey));
        }

        $fixturePath = base_path('tests/Fixtures/nasa_rss.xml');
        if (!is_file($fixturePath)) {
            throw new RuntimeException('Missing benchmark fixture: tests/Fixtures/nasa_rss.xml');
        }

        $fixtureBody = file_get_contents($fixturePath);
        if ($fixtureBody === false) {
            throw new RuntimeException('Cannot read benchmark fixture file.');
        }

        $durations = [];
        $queryCounts = [];
        $publishedCounts = [];
        $fetchedCounts = [];
        $errorsCount = 0;

        $originalPrimary = (string) config('bots.translation.primary', 'dummy');
        $originalFallback = (string) config('bots.translation.fallback', 'dummy');
        config()->set('bots.translation.primary', 'dummy');
        config()->set('bots.translation.fallback', 'dummy');

        Http::fake([
            '*' => Http::response($fixtureBody, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $iterations; $i++) {
                [$durationMs, $queryCount] = $this->measureWithQueryCount(function () use ($source, &$publishedCounts, &$fetchedCounts, &$errorsCount): void {
                    $run = $this->botRunner->run(
                        source: $source,
                        runContext: 'admin',
                        forceManualOverride: true,
                        mode: 'dry',
                        publishLimit: 0
                    );

                    $stats = is_array($run->stats) ? $run->stats : [];
                    $publishedCounts[] = (int) ($stats['published_count'] ?? 0);
                    $fetchedCounts[] = (int) ($stats['fetched_count'] ?? 0);
                    $errorsCount += (int) ($stats['failed_count'] ?? 0);
                });

                $durations[] = $durationMs;
                $queryCounts[] = $queryCount;
            }

            DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        } finally {
            config()->set('bots.translation.primary', $originalPrimary);
            config()->set('bots.translation.fallback', $originalFallback);
            Http::swap(new HttpFactory());
        }

        return $this->persistMetric(
            key: 'bot_run_' . $normalizedSourceKey,
            sampleSize: $iterations,
            durationsMs: $durations,
            queryCounts: $queryCounts,
            payload: [
                'sourceKey' => $normalizedSourceKey,
                'mode' => 'dry',
                'fixture' => 'tests/Fixtures/nasa_rss.xml',
                'items_fetched' => array_sum($fetchedCounts),
                'items_published' => array_sum($publishedCounts),
                'errors_count' => $errorsCount,
                'rolled_back' => true,
            ],
            createdBy: $createdBy,
        );
    }

    /**
     * @param callable():void $callback
     * @return array{0:int,1:int}
     */
    private function measureWithQueryCount(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startNs = hrtime(true);

        try {
            $callback();
        } finally {
            $durationMs = (int) round((hrtime(true) - $startNs) / 1_000_000);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return [$durationMs, $queries];
    }

    private function dispatchEventsIndexRequest(string $mode): void
    {
        $query = [
            'per_page' => 20,
            'year' => now()->year,
        ];

        if ($mode === 'no_cache') {
            $query['_perf_no_cache'] = 1;
        }

        $request = Request::create('/api/events', 'GET', $query, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->httpKernel->handle($request);
        $status = $response->getStatusCode();
        $this->httpKernel->terminate($request, $response);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Events benchmark request failed with HTTP %d.', $status));
        }
    }

    /**
     * @param list<int> $durationsMs
     * @param list<int> $queryCounts
     * @param array<string,mixed> $payload
     */
    private function persistMetric(
        string $key,
        int $sampleSize,
        array $durationsMs,
        array $queryCounts,
        array $payload,
        ?int $createdBy = null,
    ): PerformanceMetric {
        if ($durationsMs === []) {
            throw new RuntimeException('Cannot persist performance metric without samples.');
        }

        $totalDuration = array_sum($durationsMs);
        $avgMs = round($totalDuration / count($durationsMs), 2);
        $p95Ms = round($this->percentile($durationsMs, 95), 2);
        $minMs = min($durationsMs);
        $maxMs = max($durationsMs);

        $dbAvg = $queryCounts === [] ? null : round(array_sum($queryCounts) / count($queryCounts), 2);
        $dbP95 = $queryCounts === [] ? null : round($this->percentile($queryCounts, 95), 2);

        $log = PerformanceLog::query()->create([
            'key' => $key,
            'environment' => app()->environment(),
            'sample_size' => $sampleSize,
            'duration_ms' => (int) $totalDuration,
            'avg_ms' => $avgMs,
            'p95_ms' => $p95Ms,
            'min_ms' => (int) $minMs,
            'max_ms' => (int) $maxMs,
            'db_queries_avg' => $dbAvg,
            'db_queries_p95' => $dbP95,
            'payload' => $payload,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        return new PerformanceMetric($log, [
            'log_id' => $log->id,
            'key' => $key,
            'sample_size' => $sampleSize,
            'duration_ms' => (int) $totalDuration,
            'avg_ms' => $avgMs,
            'p95_ms' => $p95Ms,
            'min_ms' => (int) $minMs,
            'max_ms' => (int) $maxMs,
            'db_queries_avg' => $dbAvg,
            'db_queries_p95' => $dbP95,
            'payload' => $payload,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param list<int|float> $values
     */
    public function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        $p = max(0, min(100, $percentile));
        sort($values);

        if (count($values) === 1) {
            return (float) $values[0];
        }

        $position = (($p / 100) * (count($values) - 1));
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);

        if ($lowerIndex === $upperIndex) {
            return (float) $values[$lowerIndex];
        }

        $weight = $position - $lowerIndex;
        $lower = (float) $values[$lowerIndex];
        $upper = (float) $values[$upperIndex];

        return $lower + (($upper - $lower) * $weight);
    }

    private function normalizeSampleSize(int $n): int
    {
        return max(1, min(500, $n));
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunPerformanceMetricsRequest;
use App\Http\Resources\Admin\PerformanceLogResource;
use App\Models\PerformanceLog;
use App\Services\Performance\PerformanceRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PerformanceMetricsController extends Controller
{
    public function __construct(
        private readonly PerformanceRunner $performanceRunner,
    ) {
    }

    public function index(): JsonResponse
    {
        $latest = PerformanceLog::query()
            ->latest('created_at')
            ->limit(50)
            ->get();

        $keys = PerformanceLog::query()
            ->select('key')
            ->distinct()
            ->pluck('key');

        $trends = [];
        foreach ($keys as $key) {
            $recent = PerformanceLog::query()
                ->where('key', $key)
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->reverse()
                ->values();

            $trends[$key] = [
                'key' => $key,
                'points' => $recent->map(fn (PerformanceLog $log): array => [
                    'id' => (int) $log->id,
                    'avg_ms' => $log->avg_ms !== null ? (float) $log->avg_ms : null,
                    'p95_ms' => $log->p95_ms !== null ? (float) $log->p95_ms : null,
                    'db_queries_avg' => $log->db_queries_avg !== null ? (float) $log->db_queries_avg : null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
            ];
        }

        $lastRunPerKey = $latest
            ->groupBy('key')
            ->map(fn (Collection $group): PerformanceLog => $group->sortByDesc('created_at')->first())
            ->values();

        return response()->json([
            'logs' => PerformanceLogResource::collection($latest),
            'last_run_per_key' => PerformanceLogResource::collection($lastRunPerKey),
            'trend' => array_values($trends),
        ]);
    }

    public function run(RunPerformanceMetricsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $run = (string) $validated['run'];
        $sampleSize = isset($validated['sample_size']) ? (int) $validated['sample_size'] : 200;
        $mode = (string) ($validated['mode'] ?? 'normal');
        $botSource = (string) ($validated['bot_source'] ?? 'nasa_rss_breaking');
        $adminUserId = $request->user()?->id;

        $lock = Cache::lock('performance_runner', 600);
        if (!$lock->get()) {
            return response()->json([
                'message' => 'Benchmark uz bezi.',
            ], 409);
        }

        try {
            $results = [];
            $logIds = [];

            if ($run === 'all') {
                $runResult = $this->performanceRunner->runAllWithOptions(
                    sampleSize: $sampleSize,
                    createdBy: $adminUserId,
                    mode: $mode,
                    botSourceKey: $botSource,
                );
                foreach ($runResult->results as $key => $metric) {
                    $results[$key] = $metric->data;
                }
                $logIds = $runResult->logIds();
            } elseif ($run === 'events_list') {
                $metric = $this->performanceRunner->runEventsListBenchmark($sampleSize, $adminUserId, $mode);
                $results['events_list'] = $metric->data;
                $logIds[] = (int) $metric->log->id;
            } elseif ($run === 'canonical') {
                $metric = $this->performanceRunner->runCanonicalMatchingBenchmark($sampleSize, $adminUserId);
                $results['canonical'] = $metric->data;
                $logIds[] = (int) $metric->log->id;
            } else {
                $metric = $this->performanceRunner->runBotImportBenchmark($botSource, [
                    'iterations' => min(5, $sampleSize),
                    'created_by' => $adminUserId,
                ]);
                $results['bot'] = $metric->data;
                $logIds[] = (int) $metric->log->id;
            }
        } finally {
            $lock->release();
        }

        return response()->json([
            'status' => 'ok',
            'log_ids' => array_values($logIds),
            'results' => $results,
        ]);
    }
}


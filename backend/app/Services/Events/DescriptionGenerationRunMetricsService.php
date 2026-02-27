<?php

namespace App\Services\Events;

use App\Models\DescriptionGenerationRun;
use Carbon\CarbonInterface;

class DescriptionGenerationRunMetricsService
{
    /**
     * @return array{
     *   duration_seconds:float,
     *   average_seconds_per_event:float,
     *   throughput_events_per_minute:float,
     *   suggested_concurrency:int
     * }
     */
    public function summarize(DescriptionGenerationRun $run): array
    {
        $startedAt = $run->started_at instanceof CarbonInterface ? $run->started_at : $run->created_at;
        $finishedAt = $run->finished_at instanceof CarbonInterface ? $run->finished_at : now();

        $durationSeconds = 0.0;
        if ($startedAt instanceof CarbonInterface) {
            $durationSeconds = max(0.001, (float) $finishedAt->diffInRealMilliseconds($startedAt) / 1000);
        }

        $processed = max(0, (int) $run->processed);
        $averageSecondsPerEvent = $processed > 0 ? $durationSeconds / $processed : 0.0;
        $throughput = $durationSeconds > 0 ? ($processed / $durationSeconds) * 60 : 0.0;

        $currentConcurrency = max(
            1,
            (int) data_get($run->meta, 'concurrency', config('ai.ollama_safe_concurrency_default', 2))
        );

        return [
            'duration_seconds' => round($durationSeconds, 2),
            'average_seconds_per_event' => round($averageSecondsPerEvent, 2),
            'throughput_events_per_minute' => round($throughput, 2),
            'suggested_concurrency' => $this->suggestConcurrency(
                current: $currentConcurrency,
                averageSecondsPerEvent: $averageSecondsPerEvent,
                failed: (int) $run->failed
            ),
        ];
    }

    private function suggestConcurrency(int $current, float $averageSecondsPerEvent, int $failed): int
    {
        $maxRecommended = 3;

        if ($failed > 0) {
            return max(1, $current - 1);
        }

        if ($averageSecondsPerEvent >= 18.0 && $current < $maxRecommended) {
            return $current + 1;
        }

        if ($averageSecondsPerEvent <= 6.0 && $current < $maxRecommended) {
            return $current + 1;
        }

        return min($current, $maxRecommended);
    }
}

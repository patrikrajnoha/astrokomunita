<?php

namespace App\Services\Bots;

use App\Enums\BotRunStatus;
use App\Enums\BotRunFailureReason;
use App\Models\BotRun;
use App\Models\BotSource;
use Illuminate\Support\Facades\Log;

class BotRunService
{
    /**
     * @param array<string,mixed> $meta
     */
    public function startRun(BotSource $source, array $meta = []): BotRun
    {
        return BotRun::query()->create([
            'bot_identity' => $source->bot_identity?->value ?? (string) $source->bot_identity,
            'source_id' => $source->id,
            'started_at' => now(),
            'finished_at' => null,
            'status' => BotRunStatus::RUNNING->value,
            'stats' => null,
            'meta' => $meta !== [] ? $meta : null,
            'error_text' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string,mixed> $meta
     */
    public function finishRun(
        BotRun $run,
        BotRunStatus|string $status,
        array $stats = [],
        ?string $errorText = null,
        array $meta = [],
    ): BotRun {
        $statusValue = $status instanceof BotRunStatus ? $status->value : strtolower(trim((string) $status));
        $mergedMeta = array_replace(
            is_array($run->meta) ? $run->meta : [],
            $meta
        );

        $run->forceFill([
            'finished_at' => now(),
            'status' => $statusValue,
            'stats' => $stats,
            'meta' => $mergedMeta !== [] ? $mergedMeta : null,
            'error_text' => $errorText,
        ])->save();

        if ($run->source_id) {
            BotSource::query()
                ->whereKey($run->source_id)
                ->update(['last_run_at' => $run->finished_at]);
        }

        return $run->fresh() ?? $run;
    }

    public function recoverStaleRunsForSource(BotSource $source, int $recoveredByRunId, int $staleMinutes = 5): int
    {
        $thresholdMinutes = max(1, $staleMinutes);
        $cutoff = now()->subMinutes($thresholdMinutes);
        $now = now();

        $staleRuns = BotRun::query()
            ->where('source_id', $source->id)
            ->whereNull('finished_at')
            ->where('created_at', '<', $cutoff)
            ->where('id', '!=', $recoveredByRunId)
            ->orderBy('id')
            ->get();

        foreach ($staleRuns as $staleRun) {
            $meta = is_array($staleRun->meta) ? $staleRun->meta : [];
            $meta['failure_reason'] = BotRunFailureReason::STALE_RUN_RECOVERED->value;
            $meta['recovered_at'] = $now->toIso8601String();
            $meta['recovered_by_run_id'] = $recoveredByRunId;
            $ageMinutes = max(0, (int) $staleRun->created_at?->diffInMinutes($now));

            $staleRun->forceFill([
                'status' => BotRunStatus::FAILED->value,
                'finished_at' => $now,
                'error_text' => 'Stale unfinished run was recovered automatically.',
                'meta' => $meta,
            ])->save();

            Log::warning('stale_run_recovered', [
                'run_id' => $staleRun->id,
                'source_id' => $source->id,
                'recovered_by_run_id' => $recoveredByRunId,
                'age_minutes' => $ageMinutes,
            ]);
        }

        return $staleRuns->count();
    }
}

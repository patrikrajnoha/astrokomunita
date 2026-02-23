<?php

namespace App\Services\Bots;

use App\Enums\BotRunStatus;
use App\Models\BotRun;
use App\Models\BotSource;

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
            'status' => null,
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
}

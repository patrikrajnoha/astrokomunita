<?php

namespace App\Services\Bots;

use App\Enums\BotRunStatus;
use App\Models\BotRun;
use App\Models\BotSource;

class BotRunService
{
    public function startRun(BotSource $source): BotRun
    {
        return BotRun::query()->create([
            'bot_identity' => $source->bot_identity?->value ?? (string) $source->bot_identity,
            'source_id' => $source->id,
            'started_at' => now(),
            'finished_at' => null,
            'status' => null,
            'stats' => null,
            'error_text' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function finishRun(
        BotRun $run,
        BotRunStatus|string $status,
        array $stats = [],
        ?string $errorText = null
    ): BotRun {
        $statusValue = $status instanceof BotRunStatus ? $status->value : strtolower(trim((string) $status));

        $run->forceFill([
            'finished_at' => now(),
            'status' => $statusValue,
            'stats' => $stats,
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


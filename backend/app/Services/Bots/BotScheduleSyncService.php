<?php

namespace App\Services\Bots;

use App\Models\BotSchedule;
use App\Models\BotSource;

class BotScheduleSyncService
{
    public function __construct(
        private readonly BotIdentityUserSyncService $botIdentityUserSyncService,
    ) {
    }

    /**
     * @return array{created:int,skipped:int,total:int}
     */
    public function syncDefaults(): array
    {
        $definitions = $this->definitions();
        $created = 0;
        $skipped = 0;

        foreach ($definitions as $definition) {
            $source = BotSource::query()
                ->where('key', $definition['source_key'])
                ->first();

            if (!$source) {
                $skipped++;
                continue;
            }

            $botUser = $this->botIdentityUserSyncService->ensureBotUser($definition['bot_identity']);

            $sourceSpecificScheduleExists = BotSchedule::query()
                ->where('source_id', $source->id)
                ->exists();

            if ($sourceSpecificScheduleExists) {
                $skipped++;
                continue;
            }

            $catchAllScheduleExists = BotSchedule::query()
                ->where('bot_user_id', $botUser->id)
                ->whereNull('source_id')
                ->exists();

            if ($catchAllScheduleExists) {
                $skipped++;
                continue;
            }

            BotSchedule::query()->create([
                'bot_user_id' => $botUser->id,
                'source_id' => $source->id,
                'enabled' => true,
                'interval_minutes' => $definition['interval_minutes'],
                'jitter_seconds' => $definition['jitter_seconds'],
                'timezone' => $definition['timezone'],
                'next_run_at' => now(),
                'last_run_at' => null,
                'last_result' => null,
                'last_message' => null,
            ]);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => count($definitions),
        ];
    }

    /**
     * @return array<int,array{source_key:string,bot_identity:string,interval_minutes:int,jitter_seconds:int,timezone:?string}>
     */
    private function definitions(): array
    {
        $rows = (array) config('bots.default_schedules', []);
        $definitions = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sourceKey = strtolower(trim((string) ($row['source_key'] ?? '')));
            $botIdentity = strtolower(trim((string) ($row['bot_identity'] ?? '')));
            $intervalMinutes = max(1, (int) ($row['interval_minutes'] ?? 0));
            $jitterSeconds = max(0, (int) ($row['jitter_seconds'] ?? 0));
            $timezone = trim((string) ($row['timezone'] ?? ''));

            if ($sourceKey === '' || $botIdentity === '') {
                continue;
            }

            $definitions[] = [
                'source_key' => $sourceKey,
                'bot_identity' => $botIdentity,
                'interval_minutes' => $intervalMinutes,
                'jitter_seconds' => $jitterSeconds,
                'timezone' => $timezone !== '' ? $timezone : null,
            ];
        }

        return $definitions;
    }
}

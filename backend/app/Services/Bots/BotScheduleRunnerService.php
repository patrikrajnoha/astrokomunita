<?php

namespace App\Services\Bots;

use App\Enums\BotRunStatus;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotScheduleRunnerService
{
    public function __construct(
        private readonly BotRunner $runner,
        private readonly BotRateLimiterService $rateLimiterService,
        private readonly BotActivityLogService $activityLogService,
    ) {
    }

    /**
     * @return Collection<int,BotSchedule>
     */
    public function dueSchedules(int $limit = 20): Collection
    {
        $normalizedLimit = max(1, min(100, $limit));

        return BotSchedule::query()
            ->with([
                'botUser:id,username,role,is_bot',
                'source:id,key,name,bot_identity,source_type,url,is_enabled',
            ])
            ->where('enabled', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->limit($normalizedLimit)
            ->get();
    }

    /**
     * @return array<string,mixed>
     */
    public function runDueSchedules(int $limit = 20): array
    {
        $schedules = $this->dueSchedules($limit);

        $stats = [
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'schedule_ids' => [],
        ];

        foreach ($schedules as $schedule) {
            $stats['processed_count']++;
            $stats['schedule_ids'][] = $schedule->id;

            $result = $this->runOneSchedule($schedule);
            if ($result === 'success') {
                $stats['success_count']++;
            } elseif ($result === 'failed') {
                $stats['failed_count']++;
            } else {
                $stats['skipped_count']++;
            }
        }

        return $stats;
    }

    private function runOneSchedule(BotSchedule $schedule): string
    {
        $botUser = $schedule->botUser;
        if (!$schedule->enabled || !$botUser) {
            $this->markSchedule($schedule, 'skipped', 'Schedule disabled or bot user missing.');
            $this->activityLogService->record(
                action: 'schedule',
                outcome: 'skipped',
                source: $schedule->source,
                reason: 'disabled_or_missing_bot_user',
                runContext: 'scheduled',
                message: 'Schedule disabled or bot user missing.',
                meta: [
                    'schedule_id' => $schedule->id,
                ]
            );

            return 'skipped';
        }

        if ($schedule->source_id && (!$schedule->source || !$schedule->source->is_enabled)) {
            $message = 'Schedule source is missing or disabled.';
            $this->markSchedule($schedule, 'skipped', $message);
            $this->activityLogService->record(
                action: 'schedule',
                outcome: 'skipped',
                source: $schedule->source,
                reason: 'source_missing_or_disabled',
                runContext: 'scheduled',
                message: $message,
                meta: [
                    'schedule_id' => $schedule->id,
                ]
            );

            return 'skipped';
        }

        $identity = $this->resolveIdentity($botUser, $schedule->source);
        $rateState = $this->rateLimiterService->resolveScheduleState($identity);
        if (($rateState['limited'] ?? false) === true) {
            $retryAfter = max(1, (int) ($rateState['retry_after_sec'] ?? 1));
            $nextRun = $this->nextRunAt($schedule);
            if ($nextRun->diffInSeconds(now(), false) < $retryAfter) {
                $nextRun = now()->addSeconds($retryAfter);
            }

            $message = sprintf('Schedule rate limited for %s. Retry after %ds.', $identity, $retryAfter);
            $this->markSchedule($schedule, 'skipped', $message, $nextRun);
            $this->activityLogService->record(
                action: 'schedule',
                outcome: 'skipped',
                source: $schedule->source,
                reason: 'schedule_rate_limited',
                runContext: 'scheduled',
                message: $message,
                meta: [
                    'schedule_id' => $schedule->id,
                    'bot_identity' => $identity,
                    'retry_after_sec' => $retryAfter,
                    'max_attempts' => (int) ($rateState['max_attempts'] ?? 0),
                    'window_sec' => (int) ($rateState['window_sec'] ?? 0),
                ]
            );

            return 'skipped';
        }

        try {
            $this->rateLimiterService->consume($rateState);
            $runs = $this->executeScheduleRuns($schedule, $identity);
            if ($runs === []) {
                $message = sprintf('No enabled sources found for bot identity "%s".', $identity);
                $this->markSchedule($schedule, 'skipped', $message);
                $this->activityLogService->record(
                    action: 'schedule',
                    outcome: 'skipped',
                    source: $schedule->source,
                    reason: 'no_enabled_sources',
                    runContext: 'scheduled',
                    message: $message,
                    meta: [
                        'schedule_id' => $schedule->id,
                        'bot_identity' => $identity,
                    ]
                );

                return 'skipped';
            }

            $aggregate = $this->aggregateRunStatus($runs);
            $message = sprintf('%d run(s) finished with %s.', count($runs), $aggregate);
            $result = $aggregate === 'failed' ? 'failed' : ($aggregate === 'skipped' ? 'skipped' : 'success');

            $this->markSchedule($schedule, $result, $message);
            $this->activityLogService->record(
                action: 'schedule',
                outcome: $result,
                source: $schedule->source,
                reason: null,
                runContext: 'scheduled',
                message: $message,
                meta: [
                    'schedule_id' => $schedule->id,
                    'bot_identity' => $identity,
                    'run_ids' => array_values(array_filter(
                        array_map(static fn ($run): ?int => $run?->id ? (int) $run->id : null, $runs)
                    )),
                ]
            );

            return $result;
        } catch (Throwable $exception) {
            $message = $this->limitText($exception->getMessage(), 280) ?? 'Schedule run failed.';
            $this->markSchedule($schedule, 'failed', $message);
            $this->activityLogService->record(
                action: 'schedule',
                outcome: 'failed',
                source: $schedule->source,
                reason: 'exception',
                runContext: 'scheduled',
                message: $message,
                meta: [
                    'schedule_id' => $schedule->id,
                    'bot_identity' => $identity,
                    'exception_class' => $exception::class,
                ]
            );
            Log::warning('Bot schedule execution failed.', [
                'schedule_id' => $schedule->id,
                'bot_identity' => $identity,
                'error' => $message,
            ]);

            return 'failed';
        }
    }

    /**
     * @return array<int,\App\Models\BotRun>
     */
    private function executeScheduleRuns(BotSchedule $schedule, string $identity): array
    {
        if ($schedule->source && $schedule->source->is_enabled) {
            return [
                $this->runner->run($schedule->source, 'scheduled', false, 'auto'),
            ];
        }

        $sources = BotSource::query()
            ->where('is_enabled', true)
            ->where('bot_identity', $identity)
            ->orderBy('key')
            ->get();

        $runs = [];
        foreach ($sources as $source) {
            $runs[] = $this->runner->run($source, 'scheduled', false, 'auto');
        }

        return $runs;
    }

    /**
     * @param array<int,\App\Models\BotRun> $runs
     */
    private function aggregateRunStatus(array $runs): string
    {
        $hasFailed = false;
        $hasPartial = false;
        $allSkipped = true;

        foreach ($runs as $run) {
            $status = strtolower(trim((string) ($run->status?->value ?? $run->status)));
            if ($status === BotRunStatus::FAILED->value) {
                $hasFailed = true;
            }
            if ($status === BotRunStatus::PARTIAL->value) {
                $hasPartial = true;
            }
            if ($status !== BotRunStatus::SKIPPED->value) {
                $allSkipped = false;
            }
        }

        if ($hasFailed) {
            return 'failed';
        }
        if ($hasPartial) {
            return 'partial';
        }
        if ($allSkipped) {
            return 'skipped';
        }

        return 'success';
    }

    private function nextRunAt(BotSchedule $schedule): \Carbon\Carbon
    {
        $minutes = max(1, (int) $schedule->interval_minutes);
        $jitterSeconds = max(0, (int) $schedule->jitter_seconds);
        $jitterValue = $jitterSeconds > 0 ? random_int(0, $jitterSeconds) : 0;

        return now()->addMinutes($minutes)->addSeconds($jitterValue);
    }

    private function markSchedule(
        BotSchedule $schedule,
        string $result,
        ?string $message,
        ?\Carbon\Carbon $nextRunAt = null
    ): void {
        $schedule->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $nextRunAt ?? $this->nextRunAt($schedule),
            'last_result' => $this->limitText($result, 20),
            'last_message' => $this->limitText((string) $message, 500),
        ])->save();
    }

    private function resolveIdentity(User $botUser, ?BotSource $source): string
    {
        $sourceIdentity = strtolower(trim((string) ($source?->bot_identity?->value ?? $source?->bot_identity ?? '')));
        if ($sourceIdentity !== '') {
            return $sourceIdentity;
        }

        $username = strtolower(trim((string) $botUser->username));
        $configIdentities = (array) config('bots.identities', []);
        foreach ($configIdentities as $identity => $definition) {
            $candidateUsername = strtolower(trim((string) data_get($definition, 'username')));
            if ($candidateUsername !== '' && $candidateUsername === $username) {
                return strtolower(trim((string) $identity));
            }
        }

        if (str_contains($username, 'stela')) {
            return 'stela';
        }

        if (str_contains($username, 'kozmo')) {
            return 'kozmo';
        }

        return 'unknown';
    }

    private function limitText(string $value, int $maxLength): ?string
    {
        $normalized = trim($value);
        if ($normalized === '' || $maxLength <= 0) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}

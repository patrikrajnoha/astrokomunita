<?php

namespace App\Services\Newsletter;

use App\Jobs\SendNewsletterToUserJob;
use App\Mail\WeeklyNewsletterMail;
use App\Models\NewsletterFeaturedEvent;
use App\Models\NewsletterRun;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterDispatchService
{
    public function __construct(
        private readonly NewsletterSelectionService $selectionService,
    ) {
    }

    /**
     * @return array{created: bool, reason: string, run: ?NewsletterRun}
     */
    public function dispatchWeeklyNewsletter(?User $adminUser = null, bool $forced = false, bool $dryRun = false): array
    {
        $range = $this->selectionService->getNextWeekRange();
        $weekStartDate = $range['week_start_date'];
        $lockKey = 'newsletter:dispatch:' . $weekStartDate;
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            return [
                'created' => false,
                'reason' => 'locked',
                'run' => $this->latestRunForWeek($weekStartDate),
            ];
        }

        $run = null;

        try {
            $runningRun = NewsletterRun::query()
                ->whereDate('week_start_date', $weekStartDate)
                ->whereIn('status', [NewsletterRun::STATUS_PENDING, NewsletterRun::STATUS_RUNNING])
                ->latest('id')
                ->first();

            if ($runningRun) {
                return [
                    'created' => false,
                    'reason' => 'already_running',
                    'run' => $runningRun,
                ];
            }

            if (! $forced && ! $dryRun) {
                $completedRun = NewsletterRun::query()
                    ->whereDate('week_start_date', $weekStartDate)
                    ->where('status', NewsletterRun::STATUS_COMPLETED)
                    ->where('dry_run', false)
                    ->latest('id')
                    ->first();

                if ($completedRun) {
                    return [
                        'created' => false,
                        'reason' => 'already_completed',
                        'run' => $completedRun,
                    ];
                }
            }

            $payload = $this->selectionService->buildNewsletterPayload();

            $recipientIds = User::query()
                ->where('newsletter_subscribed', true)
                ->where('is_active', true)
                ->where('is_bot', false)
                ->whereNotNull('email')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $originalRecipientCount = count($recipientIds);
            $maxRecipientsPerRun = max(0, (int) config('newsletter.max_recipients_per_run', 0));
            if ($maxRecipientsPerRun > 0 && $originalRecipientCount > $maxRecipientsPerRun) {
                $recipientIds = array_slice($recipientIds, 0, $maxRecipientsPerRun);
            }

            $run = NewsletterRun::query()->create([
                'week_start_date' => $weekStartDate,
                'status' => NewsletterRun::STATUS_RUNNING,
                'total_recipients' => count($recipientIds),
                'sent_count' => 0,
                'failed_count' => 0,
                'started_at' => now(),
                'finished_at' => null,
                'admin_user_id' => $adminUser?->id,
                'forced' => $forced,
                'dry_run' => $dryRun,
                'error' => null,
                'meta' => [
                    'payload' => $payload,
                    'trigger' => $adminUser ? 'admin' : 'scheduler',
                    'triggered_at' => now()->toIso8601String(),
                    'max_recipients_per_run' => $maxRecipientsPerRun,
                    'original_recipient_count' => $originalRecipientCount,
                ],
            ]);

            $payloadWithRun = array_merge($payload, [
                'run' => [
                    'id' => $run->id,
                    'week_start_date' => $weekStartDate,
                    'forced' => $forced,
                    'dry_run' => $dryRun,
                ],
            ]);

            $this->snapshotFeaturedEventsForRun($run, (array) ($payloadWithRun['top_events'] ?? []));

            if ($run->total_recipients === 0) {
                $run->forceFill([
                    'status' => NewsletterRun::STATUS_COMPLETED,
                    'finished_at' => now(),
                ])->save();

                Log::channel('newsletter')->info('Newsletter run completed with no recipients.', [
                    'run_id' => $run->id,
                    'week_start_date' => $weekStartDate,
                    'dry_run' => $dryRun,
                    'forced' => $forced,
                ]);

                return [
                    'created' => true,
                    'reason' => 'no_recipients',
                    'run' => $run->fresh(),
                ];
            }

            $batchSize = $this->resolveBatchSize();
            $batchDelayMs = $this->resolveBatchDelayMs($batchSize);
            $queueName = (string) config('newsletter.queue', 'default');

            foreach (array_chunk($recipientIds, $batchSize) as $batchIndex => $chunk) {
                $pendingDispatch = SendNewsletterToUserJob::dispatch(
                    runId: (int) $run->id,
                    userIds: array_values($chunk),
                    payload: $payloadWithRun,
                    dryRun: $dryRun
                )->onQueue($queueName);

                if ($batchDelayMs > 0 && $batchIndex > 0) {
                    $pendingDispatch->delay(now()->addMilliseconds($batchDelayMs * $batchIndex));
                }
            }

            Log::channel('newsletter')->info('Newsletter run dispatched.', [
                'run_id' => $run->id,
                'week_start_date' => $weekStartDate,
                'total_recipients' => $run->total_recipients,
                'batch_size' => $batchSize,
                'batch_delay_ms' => $batchDelayMs,
                'max_recipients_per_run' => $maxRecipientsPerRun,
                'dry_run' => $dryRun,
                'forced' => $forced,
                'admin_user_id' => $adminUser?->id,
            ]);

            return [
                'created' => true,
                'reason' => 'dispatched',
                'run' => $run->fresh(),
            ];
        } catch (\Throwable $exception) {
            if ($run instanceof NewsletterRun) {
                $run->forceFill([
                    'status' => NewsletterRun::STATUS_FAILED,
                    'finished_at' => now(),
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                    'meta' => array_merge((array) $run->meta, [
                        'error' => $exception->getMessage(),
                    ]),
                ])->save();
            }

            Log::channel('newsletter')->error('Newsletter dispatch failed.', [
                'week_start_date' => $weekStartDate,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function sendToUser(User $user, array $payload): bool
    {
        if (! $user->newsletter_subscribed || ! $user->is_active || $user->is_bot) {
            return false;
        }

        $email = trim((string) $user->email);
        if ($email === '') {
            return false;
        }

        $run = (array) ($payload['run'] ?? []);
        $forced = (bool) ($run['forced'] ?? false);
        $runId = (int) ($run['id'] ?? 0);
        $weekStartDate = (string) ($run['week_start_date'] ?? data_get($payload, 'week.start', ''));

        $idempotencyKey = $forced
            ? sprintf('newsletter:sent:run:%d:user:%d', $runId, (int) $user->id)
            : sprintf('newsletter:sent:week:%s:user:%d', $weekStartDate, (int) $user->id);

        $ttl = now()->addDays(15);
        if (! Cache::add($idempotencyKey, true, $ttl)) {
            return false;
        }

        Mail::to($email)->send(new WeeklyNewsletterMail($payload, $user));

        return true;
    }

    public function sendPreviewToUser(User $user, array $payload): bool
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return false;
        }

        Mail::to($email)->send(new WeeklyNewsletterMail($payload, $user, true));

        return true;
    }

    public function recordPreviewDispatch(?User $adminUser = null): NewsletterRun
    {
        $weekStartDate = (string) $this->selectionService->getNextWeekRange()['week_start_date'];

        $runQuery = NewsletterRun::query()
            ->whereDate('week_start_date', $weekStartDate)
            ->where('status', NewsletterRun::STATUS_COMPLETED)
            ->where('dry_run', true)
            ->where('total_recipients', 0);

        if ($adminUser?->id) {
            $runQuery->where('admin_user_id', (int) $adminUser->id);
        } else {
            $runQuery->whereNull('admin_user_id');
        }

        $run = $runQuery->latest('id')->first();

        if (! $run) {
            $run = NewsletterRun::query()->create([
                'week_start_date' => $weekStartDate,
                'status' => NewsletterRun::STATUS_COMPLETED,
                'total_recipients' => 0,
                'sent_count' => 0,
                'preview_count' => 0,
                'unsubscribe_count' => 0,
                'failed_count' => 0,
                'started_at' => now(),
                'finished_at' => now(),
                'admin_user_id' => $adminUser?->id,
                'forced' => true,
                'dry_run' => true,
                'error' => null,
                'meta' => [
                    'trigger' => 'preview',
                    'triggered_at' => now()->toIso8601String(),
                ],
            ]);
        }

        NewsletterRun::query()
            ->whereKey($run->id)
            ->update([
                'preview_count' => DB::raw('preview_count + 1'),
                'updated_at' => now(),
            ]);

        return $run->fresh();
    }

    public function incrementUnsubscribeCount(int $runId): void
    {
        NewsletterRun::query()
            ->whereKey($runId)
            ->update([
                'unsubscribe_count' => DB::raw('unsubscribe_count + 1'),
                'updated_at' => now(),
            ]);
    }

    public function markUserSent(int $runId): void
    {
        NewsletterRun::query()
            ->whereKey($runId)
            ->update([
                'sent_count' => DB::raw('sent_count + 1'),
                'updated_at' => now(),
            ]);

        $this->markRunCompletedIfDone($runId);
    }

    public function markUserFailed(int $runId, ?string $reason = null): void
    {
        NewsletterRun::query()
            ->whereKey($runId)
            ->update([
                'failed_count' => DB::raw('failed_count + 1'),
                'updated_at' => now(),
            ]);

        if ($reason) {
            Log::channel('newsletter')->warning('Newsletter recipient failed.', [
                'run_id' => $runId,
                'reason' => $reason,
            ]);
        }

        $this->markRunCompletedIfDone($runId);
    }

    public function markRunCompletedIfDone(int $runId): void
    {
        $run = NewsletterRun::query()->find($runId);
        if (! $run) {
            return;
        }

        if (! in_array($run->status, [NewsletterRun::STATUS_PENDING, NewsletterRun::STATUS_RUNNING], true)) {
            return;
        }

        $processed = (int) $run->sent_count + (int) $run->failed_count;
        if ($processed < (int) $run->total_recipients) {
            return;
        }

        $updated = NewsletterRun::query()
            ->whereKey($runId)
            ->whereIn('status', [NewsletterRun::STATUS_PENDING, NewsletterRun::STATUS_RUNNING])
            ->update([
                'status' => NewsletterRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::channel('newsletter')->info('Newsletter run completed.', [
                'run_id' => $runId,
                'week_start_date' => $run->week_start_date?->toDateString(),
                'total_recipients' => $run->total_recipients,
                'sent_count' => $run->fresh()->sent_count,
                'failed_count' => $run->fresh()->failed_count,
                'dry_run' => $run->dry_run,
                'forced' => $run->forced,
            ]);
        }
    }

    public function listRuns(int $perPage = 20): LengthAwarePaginator
    {
        return NewsletterRun::query()
            ->with('adminUser:id,name,email')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    private function resolveBatchSize(): int
    {
        $batchSize = (int) config('newsletter.batch_size', 0);
        if ($batchSize <= 0) {
            $batchSize = (int) config('newsletter.chunk_size', 100);
        }

        return max(1, $batchSize);
    }

    private function resolveBatchDelayMs(int $batchSize): int
    {
        if (app()->runningUnitTests() && (bool) config('newsletter.disable_throttling_in_tests', true)) {
            return 0;
        }

        $explicitDelayMs = max(0, (int) config('newsletter.sleep_ms_between_batches', 0));
        if ($explicitDelayMs > 0) {
            return $explicitDelayMs;
        }

        $rateLimitPerMinute = max(0, (int) config('newsletter.rate_limit_per_minute', 0));
        if ($rateLimitPerMinute <= 0) {
            return 0;
        }

        return (int) ceil(($batchSize / $rateLimitPerMinute) * 60000);
    }

    private function latestRunForWeek(string $weekStartDate): ?NewsletterRun
    {
        return NewsletterRun::query()
            ->whereDate('week_start_date', $weekStartDate)
            ->latest('id')
            ->first();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function snapshotFeaturedEventsForRun(NewsletterRun $run, array $events): void
    {
        $rows = [];
        $timestamp = now();

        foreach (array_slice($events, 0, NewsletterSelectionService::MAX_FEATURED_EVENTS) as $index => $event) {
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $rows[] = [
                'newsletter_run_id' => $run->id,
                'event_id' => $eventId,
                'order' => $index,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows !== []) {
            NewsletterFeaturedEvent::query()->insert($rows);
        }
    }
}

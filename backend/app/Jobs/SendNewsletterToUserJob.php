<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Newsletter\NewsletterDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewsletterToUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param array<int, int> $userIds
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $runId,
        public readonly array $userIds,
        public readonly array $payload,
        public readonly bool $dryRun = false,
    ) {
    }

    public function handle(NewsletterDispatchService $dispatchService): void
    {
        $usersById = User::query()
            ->whereIn('id', $this->userIds)
            ->get()
            ->keyBy('id');

        foreach ($this->userIds as $userId) {
            $user = $usersById->get((int) $userId);
            if (! $user) {
                $dispatchService->markUserFailed($this->runId, 'user_not_found');
                continue;
            }

            if (! $user->newsletter_subscribed || ! $user->is_active || $user->is_bot) {
                $dispatchService->markUserFailed($this->runId, 'user_not_eligible');
                continue;
            }

            if ($this->dryRun) {
                Log::channel('newsletter')->info('Newsletter dry-run recipient processed.', [
                    'run_id' => $this->runId,
                    'user_id' => (int) $user->id,
                ]);
                $dispatchService->markUserSent($this->runId);
                continue;
            }

            try {
                $sent = $dispatchService->sendToUser($user, $this->payload);
                if ($sent) {
                    $dispatchService->markUserSent($this->runId);
                } else {
                    $dispatchService->markUserFailed($this->runId, 'idempotency_or_email_guard');
                }
            } catch (\Throwable $exception) {
                $dispatchService->markUserFailed(
                    $this->runId,
                    'send_exception:' . mb_substr($exception->getMessage(), 0, 150)
                );
            }
        }
    }
}

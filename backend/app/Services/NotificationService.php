<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /** @var array<int, array<string, bool>> */
    private array $inAppPreferenceCache = [];

    public function createPostLiked(int $recipientId, int $actorId, int $postId): ?Notification
    {
        if ($recipientId === $actorId) {
            return null;
        }

        if (!$this->shouldDeliverInApp($recipientId, 'post_like')) {
            return null;
        }

        $existing = Notification::query()
            ->where('user_id', $recipientId)
            ->where('type', 'post_liked')
            ->whereNull('read_at')
            ->where('data->actor_id', $actorId)
            ->where('data->post_id', $postId)
            ->first();

        if ($existing) {
            $existing->forceFill([
                'created_at' => now(),
                'updated_at' => now(),
            ])->save();

            $existing = $existing->refresh();
            $this->broadcastNotification($existing);

            return $existing;
        }

        $actor = User::query()->select(['id', 'name', 'username'])->find($actorId);

        // TODO(notifications-email): When social notification emails are introduced,
        // check NotificationPreference.email_enabled here and dispatch email delivery.
        $notification = Notification::create([
            'user_id' => $recipientId,
            'type' => 'post_liked',
            'data' => [
                'actor_id' => $actorId,
                'actor_name' => $actor?->name,
                'actor_username' => $actor?->username,
                'post_id' => $postId,
            ],
        ]);

        $this->broadcastNotification($notification);

        return $notification;
    }

    public function createEventReminder(int $recipientId, int $eventId, string $remindAtWindowKey): ?Notification
    {
        $event = Event::query()->select(['id', 'title', 'type', 'start_at', 'max_at'])->find($eventId);
        if (!$event) {
            return null;
        }

        if (!NotificationPreference::allowsEventReminderInAppForType(
            $this->inAppPreferencesForUser($recipientId),
            $event->type,
        )) {
            return null;
        }

        $hash = sha1('event_reminder|' . $recipientId . '|' . $eventId . '|' . $remindAtWindowKey);

        if (NotificationEvent::query()->where('hash', $hash)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($recipientId, $event, $hash, $remindAtWindowKey) {
            $notification = Notification::create([
                'user_id' => $recipientId,
                'type' => 'event_reminder',
                'data' => [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'event_start_at' => optional($event->start_at ?? $event->max_at)?->toIso8601String(),
                    'reminder_window' => $remindAtWindowKey,
                ],
            ]);

            NotificationEvent::create([
                'hash' => $hash,
                'notification_id' => $notification->id,
            ]);

            $this->broadcastNotification($notification);

            // TODO(notifications-email): If event reminder emails are moved to this flow,
            // respect NotificationPreference.email_enabled before dispatching mail jobs.

            return $notification;
        });
    }

    public function createContestWinner(int $recipientId, int $contestId, string $contestName, int $postId): ?Notification
    {
        if (!$this->shouldDeliverInApp($recipientId, 'system')) {
            return null;
        }

        $notification = Notification::create([
            'user_id' => $recipientId,
            'type' => 'contest_winner',
            'data' => [
                'contest_id' => $contestId,
                'contest_name' => $contestName,
                'post_id' => $postId,
            ],
        ]);

        $this->broadcastNotification($notification);

        return $notification;
    }

    public function createEventInvite(
        int $recipientId,
        int $actorId,
        ?int $eventId = null,
        ?string $eventTitle = null,
        array $extraData = [],
    ): ?Notification {
        if ($recipientId === $actorId) {
            return null;
        }

        if (!$this->shouldDeliverInApp($recipientId, 'system')) {
            return null;
        }

        $actor = User::query()->select(['id', 'name', 'username'])->find($actorId);

        $notification = Notification::create([
            'user_id' => $recipientId,
            'type' => 'event_invite',
            'data' => array_merge([
                'actor_id' => $actorId,
                'actor_name' => $actor?->name,
                'actor_username' => $actor?->username,
                'event_id' => $eventId,
                'event_title' => $eventTitle ?: null,
            ], $extraData),
        ]);

        $this->broadcastNotification($notification);

        return $notification;
    }

    public function createEventInviteResponse(
        int $recipientId,
        int $actorId,
        ?int $eventId = null,
        ?string $eventTitle = null,
        ?string $responseStatus = null,
        array $extraData = [],
    ): ?Notification {
        if ($recipientId === $actorId) {
            return null;
        }

        if (!$this->shouldDeliverInApp($recipientId, 'system')) {
            return null;
        }

        $actor = User::query()->select(['id', 'name', 'username'])->find($actorId);

        $notification = Notification::create([
            'user_id' => $recipientId,
            'type' => 'event_invite_response',
            'data' => array_merge([
                'actor_id' => $actorId,
                'actor_name' => $actor?->name,
                'actor_username' => $actor?->username,
                'event_id' => $eventId,
                'event_title' => $eventTitle ?: null,
                'response_status' => $responseStatus,
            ], $extraData),
        ]);

        $this->broadcastNotification($notification);

        return $notification;
    }

    public function createAccountRestricted(int $recipientId, string $reason, ?int $actorId = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $recipientId,
            'type' => 'account_restricted',
            'data' => [
                'reason' => trim($reason),
                'actor_id' => $actorId,
                'restricted_at' => now()->toIso8601String(),
            ],
        ]);

        $this->broadcastNotification($notification);

        return $notification;
    }

    /**
     * @param array{
     *   next_pass_at:string,
     *   duration_sec?:int,
     *   max_altitude_deg?:float,
     *   direction_start?:string,
     *   direction_end?:string
     * } $issPreview
     */
    public function createIssPassAlert(int $recipientId, array $issPreview): ?Notification
    {
        if (!$this->shouldDeliverInApp($recipientId, 'system')) {
            return null;
        }

        $nextPassAt = trim((string) ($issPreview['next_pass_at'] ?? ''));
        if ($nextPassAt === '') {
            return null;
        }

        $hash = sha1('iss_pass_alert|' . $recipientId . '|' . $nextPassAt);
        if (NotificationEvent::query()->where('hash', $hash)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($recipientId, $issPreview, $nextPassAt, $hash) {
            $notification = Notification::create([
                'user_id' => $recipientId,
                'type' => 'iss_pass_alert',
                'data' => [
                    'next_pass_at' => $nextPassAt,
                    'duration_sec' => max(0, (int) ($issPreview['duration_sec'] ?? 0)),
                    'max_altitude_deg' => is_numeric($issPreview['max_altitude_deg'] ?? null)
                        ? round((float) $issPreview['max_altitude_deg'], 1)
                        : null,
                    'direction_start' => $issPreview['direction_start'] ?? null,
                    'direction_end' => $issPreview['direction_end'] ?? null,
                ],
            ]);

            NotificationEvent::create([
                'hash' => $hash,
                'notification_id' => $notification->id,
            ]);

            $this->broadcastNotification($notification);

            return $notification;
        });
    }

    public function createGoodConditionsAlert(int $recipientId, int $observingScore, string $localDate): ?Notification
    {
        if (!$this->shouldDeliverInApp($recipientId, 'system')) {
            return null;
        }

        $normalizedDate = trim($localDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedDate)) {
            return null;
        }

        $hash = sha1('good_conditions_alert|' . $recipientId . '|' . $normalizedDate);
        if (NotificationEvent::query()->where('hash', $hash)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($recipientId, $observingScore, $normalizedDate, $hash) {
            $notification = Notification::create([
                'user_id' => $recipientId,
                'type' => 'good_conditions_alert',
                'data' => [
                    'observing_score' => max(0, min(100, $observingScore)),
                    'local_date' => $normalizedDate,
                ],
            ]);

            NotificationEvent::create([
                'hash' => $hash,
                'notification_id' => $notification->id,
            ]);

            $this->broadcastNotification($notification);

            return $notification;
        });
    }

    public function markRead(int $notificationId, int $userId): Notification
    {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new ModelNotFoundException('Notification not found');
        }

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->refresh();
    }

    public function markAllRead(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(int $notificationId, int $userId): void
    {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new ModelNotFoundException('Notification not found');
        }

        $notification->delete();
    }

    public function deleteAll(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->delete();
    }

    public function unreadCount(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function list(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));

        return Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function shouldDeliverInApp(int $userId, string $preferenceKey): bool
    {
        return (bool) ($this->inAppPreferencesForUser($userId)[$preferenceKey] ?? true);
    }

    /**
     * @return array<string, bool>
     */
    private function inAppPreferencesForUser(int $userId): array
    {
        if (!isset($this->inAppPreferenceCache[$userId])) {
            $preferences = NotificationPreference::ensureForUser($userId);
            $this->inAppPreferenceCache[$userId] = $preferences->inApp();
        }

        return $this->inAppPreferenceCache[$userId];
    }

    private function broadcastNotification(Notification $notification): void
    {
        $dispatch = function () use ($notification) {
            try {
                $freshNotification = $notification->fresh();
                if (!$freshNotification) {
                    return;
                }

                event(new NotificationCreated($freshNotification));
            } catch (\Throwable $error) {
                if (app()->environment('local')) {
                    Log::warning('Notification realtime broadcast failed', [
                        'notification_id' => $notification->id,
                        'user_id' => $notification->user_id,
                        'type' => $notification->type,
                        'message' => $error->getMessage(),
                    ]);
                }
            }
        };

        if (DB::transactionLevel() > 0 && !app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
            return;
        }

        $dispatch();
    }
}

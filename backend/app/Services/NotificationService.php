<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function createPostLiked(int $recipientId, int $actorId, int $postId): ?Notification
    {
        if ($recipientId === $actorId) {
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

            return $existing->refresh();
        }

        $actor = User::query()->select(['id', 'name', 'username'])->find($actorId);

        return Notification::create([
            'user_id' => $recipientId,
            'type' => 'post_liked',
            'data' => [
                'actor_id' => $actorId,
                'actor_name' => $actor?->name,
                'actor_username' => $actor?->username,
                'post_id' => $postId,
            ],
        ]);
    }

    public function createEventReminder(int $recipientId, int $eventId, string $remindAtWindowKey): ?Notification
    {
        $hash = sha1('event_reminder|' . $recipientId . '|' . $eventId . '|' . $remindAtWindowKey);

        if (NotificationEvent::query()->where('hash', $hash)->exists()) {
            return null;
        }

        $event = Event::query()->select(['id', 'title', 'start_at', 'max_at'])->find($eventId);
        if (!$event) {
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
}

<?php

namespace App\Events;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(private readonly Notification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $notification = (new NotificationResource($this->notification))
            ->toArray(new Request());

        $notification['title'] = $this->titleFor($notification);
        $notification['text'] = $this->textFor($notification);

        return [
            'notification' => $notification,
        ];
    }

    private function titleFor(array $notification): string
    {
        return match ((string) ($notification['type'] ?? '')) {
            'post_liked' => 'New like',
            'event_reminder' => 'Event reminder',
            'contest_winner' => 'Contest result',
            'event_invite' => 'Event invite',
            'event_invite_response' => 'Invite response',
            'account_restricted' => 'Account notice',
            'iss_pass_alert' => 'ISS pass alert',
            'good_conditions_alert' => 'Observing conditions',
            default => 'New notification',
        };
    }

    private function textFor(array $notification): string
    {
        $type = (string) ($notification['type'] ?? '');
        $data = is_array($notification['data'] ?? null) ? $notification['data'] : [];

        return match ($type) {
            'post_liked' => $this->actorName($data) . ' liked your post.',
            'event_reminder' => 'Your saved event is coming up soon.',
            'contest_winner' => 'You won a contest.',
            'event_invite' => $this->actorName($data) . ' invited you to an event.',
            'event_invite_response' => $this->actorName($data) . ' responded to your invite.',
            'account_restricted' => 'Your account has been restricted.',
            'iss_pass_alert' => 'The ISS will be visible soon.',
            'good_conditions_alert' => 'Good observing conditions are expected today.',
            default => 'You have a new notification.',
        };
    }

    private function actorName(array $data): string
    {
        $name = trim((string) ($data['actor_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($data['actor_username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return 'Someone';
    }
}

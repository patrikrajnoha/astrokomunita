<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventPublished implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Event $event,
        private readonly string $scope = 'normal',
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('events.feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'event.published';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => (int) $this->event->id,
            'scope' => $this->scope,
            'published_at' => now()->toIso8601String(),
        ];
    }
}

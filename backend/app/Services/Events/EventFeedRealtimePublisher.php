<?php

namespace App\Services\Events;

use App\Events\EventPublished;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventFeedRealtimePublisher
{
    public function publish(Event $event, string $scope = 'normal'): void
    {
        DB::afterCommit(static function () use ($event, $scope): void {
            broadcast(new EventPublished($event, $scope));
        });
    }
}

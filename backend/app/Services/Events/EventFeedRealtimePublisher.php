<?php

namespace App\Services\Events;

use App\Events\EventPublished;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventFeedRealtimePublisher
{
    public function publish(Event $event, string $scope = 'normal'): void
    {
        $dispatch = static function () use ($event, $scope): void {
            try {
                broadcast(new EventPublished($event, $scope));
            } catch (\Throwable $error) {
                if (app()->environment('local')) {
                    Log::warning('Event realtime broadcast failed.', [
                        'event_id' => (int) $event->id,
                        'scope' => $scope,
                        'message' => $error->getMessage(),
                    ]);
                }
            }
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
            return;
        }

        $dispatch();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Favorite;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendEventNotificationReminders extends Command
{
    protected $signature = 'notifications:send-event-reminders {--window=60}';

    protected $description = 'Send in-app event reminders for events starting soon.';

    public function handle(): int
    {
        $minutes = (int) $this->option('window');
        $minutes = $minutes > 0 ? $minutes : 60;
        $now = CarbonImmutable::now();

        $start = $now->addMinutes($minutes - 5);
        $end = $now->addMinutes($minutes + 5);
        $windowKey = 'T-' . $minutes;

        $service = app(NotificationService::class);

        Event::query()
            ->published()
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->chunkById(100, function ($events) use ($service, $windowKey) {
                foreach ($events as $event) {
                    $recipientIds = Favorite::query()
                        ->where('event_id', $event->id)
                        ->pluck('user_id');

                    if ($recipientIds->isEmpty()) {
                        $recipientIds = User::query()
                            ->where('is_active', true)
                            ->where('is_bot', false)
                            ->pluck('id');
                    }

                    foreach ($recipientIds as $recipientId) {
                        $service->createEventReminder(
                            (int) $recipientId,
                            (int) $event->id,
                            $windowKey
                        );
                    }
                }
            });

        return Command::SUCCESS;
    }
}

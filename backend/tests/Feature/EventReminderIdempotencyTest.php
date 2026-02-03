<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EventReminderIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_idempotent_per_window(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 12:00:00');

        $user = User::factory()->create();
        $event = Event::create([
            'title' => 'Test event',
            'type' => 'other',
            'start_at' => CarbonImmutable::now()->addMinutes(60),
            'end_at' => CarbonImmutable::now()->addMinutes(120),
            'max_at' => CarbonImmutable::now()->addMinutes(60),
            'visibility' => 1,
            'source_name' => 'test-source',
            'source_uid' => uniqid('uid_', true),
        ]);

        EventCandidate::create([
            'source_name' => 'test-source',
            'source_hash' => sha1(uniqid('hash_', true)),
            'title' => 'Candidate',
            'type' => 'other',
            'max_at' => $event->start_at,
            'start_at' => $event->start_at,
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        Artisan::call('notifications:send-event-reminders');
        Artisan::call('notifications:send-event-reminders');

        $this->assertEquals(1, Notification::query()->count());
        $this->assertEquals(1, NotificationEvent::query()->count());

        $notification = Notification::query()->first();
        $this->assertEquals('event_reminder', $notification->type);
        $this->assertEquals('T-60', $notification->data['reminder_window']);
        CarbonImmutable::setTestNow();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\EventReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendEventRemindersPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_send_skips_email_when_email_preferences_are_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->createEvent();

        NotificationPreference::ensureForUser($user->id)->update([
            'email_enabled' => false,
            'email_json' => [
                'event_reminder' => false,
            ],
        ]);

        EventReminder::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'minutes_before' => 60,
            'remind_at' => CarbonImmutable::now()->subMinute(),
            'status' => 'pending',
        ]);

        Artisan::call('reminders:send');

        Notification::assertNothingSent();
        $this->assertDatabaseHas('event_reminders', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'event_reminder',
        ]);
    }

    public function test_reminders_send_sends_email_when_email_preferences_allow_it(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->createEvent();

        NotificationPreference::ensureForUser($user->id)->update([
            'email_enabled' => true,
            'email_json' => [
                'event_reminder' => true,
            ],
        ]);

        EventReminder::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'minutes_before' => 60,
            'remind_at' => CarbonImmutable::now()->subMinute(),
            'status' => 'pending',
        ]);

        Artisan::call('reminders:send');

        Notification::assertSentTo($user, EventReminderNotification::class);
        $this->assertDatabaseHas('event_reminders', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'event_reminder',
        ]);
    }

    private function createEvent(): Event
    {
        $startAt = CarbonImmutable::now()->addHours(2);

        return Event::create([
            'title' => 'Test event',
            'type' => 'other',
            'start_at' => $startAt,
            'end_at' => $startAt->addHour(),
            'max_at' => $startAt,
            'visibility' => 1,
            'source_name' => 'test-source',
            'source_uid' => uniqid('event_', true),
        ]);
    }
}
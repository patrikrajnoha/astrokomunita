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

    public function test_reminders_send_skips_email_when_specific_event_type_email_is_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->createEvent('Perzeidy', 'meteor_shower');

        NotificationPreference::ensureForUser($user->id)->update([
            'email_enabled' => true,
            'email_json' => [
                'event_reminder' => true,
                'event_reminder_meteors' => false,
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
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'event_reminder',
        ]);
    }

    public function test_reminders_send_skips_in_app_notification_when_specific_event_type_is_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->createEvent('Perzeidy', 'meteor_shower');

        NotificationPreference::ensureForUser($user->id)->update([
            'in_app_json' => [
                'post_like' => true,
                'post_comment' => true,
                'reply' => true,
                'event_reminder' => true,
                'event_reminder_meteors' => false,
                'event_reminder_eclipses' => true,
                'event_reminder_planetary' => true,
                'event_reminder_small_bodies' => true,
                'event_reminder_aurora' => true,
                'event_reminder_space' => true,
                'event_reminder_observing' => true,
                'system' => true,
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

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => 'event_reminder',
        ]);
        $this->assertDatabaseHas('event_reminders', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'sent',
        ]);
    }

    private function createEvent(string $title = 'Test event', string $type = 'other'): Event
    {
        $startAt = CarbonImmutable::now()->addHours(2);

        return Event::create([
            'title' => $title,
            'type' => $type,
            'start_at' => $startAt,
            'end_at' => $startAt->addHour(),
            'max_at' => $startAt,
            'visibility' => 1,
            'source_name' => 'test-source',
            'source_uid' => uniqid('event_', true),
        ]);
    }
}

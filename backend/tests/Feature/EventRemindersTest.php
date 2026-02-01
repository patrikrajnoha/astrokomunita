<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventReminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_create_validates_time(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent(CarbonImmutable::now()->addMinutes(30));

        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$event->id}/reminders", [
            'minutes_before' => 60,
        ])->assertStatus(422);

        $this->postJson("/api/events/{$event->id}/reminders", [
            'minutes_before' => 15,
        ])->assertOk();
    }

    public function test_reminder_delete_owner_only(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $event = $this->createPublishedEvent(CarbonImmutable::now()->addHours(3));

        $reminder = EventReminder::create([
            'user_id' => $userA->id,
            'event_id' => $event->id,
            'minutes_before' => 60,
            'remind_at' => CarbonImmutable::now()->addHours(2),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($userB);
        $this->deleteJson("/api/reminders/{$reminder->id}")->assertStatus(403);

        Sanctum::actingAs($userA);
        $this->deleteJson("/api/reminders/{$reminder->id}")->assertOk();
        $this->assertDatabaseMissing('event_reminders', ['id' => $reminder->id]);
    }

    public function test_events_range_returns_correct_set(): void
    {
        $inRangeA = $this->createPublishedEvent(CarbonImmutable::parse('2026-01-10 10:00:00'));
        $inRangeB = $this->createPublishedEvent(CarbonImmutable::parse('2026-01-15 12:00:00'));
        $outRange = $this->createPublishedEvent(CarbonImmutable::parse('2026-02-01 10:00:00'));

        $res = $this->getJson('/api/events?from=2026-01-01&to=2026-01-31');
        $res->assertOk();

        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($inRangeA->id, $ids);
        $this->assertContains($inRangeB->id, $ids);
        $this->assertNotContains($outRange->id, $ids);
    }

    private function createPublishedEvent(CarbonImmutable $startAt): Event
    {
        $event = Event::create([
            'title' => 'Test event',
            'type' => 'other',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHour(),
            'max_at' => $startAt,
            'visibility' => 1,
            'source_name' => 'test-source',
            'source_uid' => uniqid('uid_', true),
        ]);

        EventCandidate::create([
            'source_name' => 'test-source',
            'source_hash' => sha1(uniqid('hash_', true)),
            'title' => 'Candidate',
            'type' => 'other',
            'max_at' => $startAt,
            'start_at' => $startAt,
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        return $event;
    }
}

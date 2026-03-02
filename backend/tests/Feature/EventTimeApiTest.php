<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTimeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_detail_preserves_existing_fields_and_adds_time_metadata(): void
    {
        $event = Event::query()->create([
            'title' => 'Peak event',
            'type' => 'other',
            'start_at' => '2026-08-12 21:00:00',
            'max_at' => '2026-08-12 21:00:00',
            'time_type' => 'peak',
            'time_precision' => 'exact',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'peak-event',
            'source_hash' => sha1('peak-event'),
        ]);
        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_hash' => sha1('peak-event-candidate'),
            'title' => 'Peak event candidate',
            'type' => 'other',
            'start_at' => '2026-08-12 21:00:00',
            'max_at' => '2026-08-12 21:00:00',
            'time_type' => 'peak',
            'time_precision' => 'exact',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.start_at', '2026-08-12T21:00:00+00:00')
            ->assertJsonPath('data.max_at', '2026-08-12T21:00:00+00:00')
            ->assertJsonPath('data.time_type', 'peak')
            ->assertJsonPath('data.time_precision', 'exact');
    }

    public function test_event_detail_falls_back_to_unknown_when_midnight_time_is_unqualified(): void
    {
        $event = Event::query()->create([
            'title' => 'Unknown-time event',
            'type' => 'meteor_shower',
            'start_at' => '2026-05-06 00:00:00',
            'max_at' => '2026-05-06 00:00:00',
            'time_type' => 'peak',
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'unknown-time-event',
            'source_hash' => sha1('unknown-time-event'),
        ]);
        EventCandidate::query()->create([
            'source_name' => 'imo',
            'source_hash' => sha1('unknown-time-event-candidate'),
            'title' => 'Unknown-time event candidate',
            'type' => 'meteor_shower',
            'start_at' => '2026-05-06 00:00:00',
            'max_at' => '2026-05-06 00:00:00',
            'time_type' => 'peak',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.start_at', '2026-05-06T00:00:00+00:00')
            ->assertJsonPath('data.max_at', '2026-05-06T00:00:00+00:00')
            ->assertJsonPath('data.time_type', 'peak')
            ->assertJsonPath('data.time_precision', 'unknown');
    }

    public function test_event_detail_preserves_explicit_midnight_when_precision_is_exact(): void
    {
        $event = Event::query()->create([
            'title' => 'Exact midnight event',
            'type' => 'meteor_shower',
            'start_at' => '2026-05-06 00:00:00',
            'max_at' => '2026-05-06 00:00:00',
            'time_type' => 'peak',
            'time_precision' => 'exact',
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'exact-midnight-event',
            'source_hash' => sha1('exact-midnight-event'),
        ]);
        EventCandidate::query()->create([
            'source_name' => 'imo',
            'source_hash' => sha1('exact-midnight-event-candidate'),
            'title' => 'Exact midnight event candidate',
            'type' => 'meteor_shower',
            'start_at' => '2026-05-06 00:00:00',
            'max_at' => '2026-05-06 00:00:00',
            'time_type' => 'peak',
            'time_precision' => 'exact',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.start_at', '2026-05-06T00:00:00+00:00')
            ->assertJsonPath('data.max_at', '2026-05-06T00:00:00+00:00')
            ->assertJsonPath('data.time_type', 'peak')
            ->assertJsonPath('data.time_precision', 'exact');
    }

    public function test_event_detail_normalizes_legacy_events_without_time_metadata(): void
    {
        $event = Event::query()->create([
            'title' => 'Legacy event',
            'type' => 'other',
            'start_at' => '2026-04-01 18:30:00',
            'max_at' => '2026-04-01 18:30:00',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'legacy-event',
            'source_hash' => sha1('legacy-event'),
        ]);
        EventCandidate::query()->create([
            'source_name' => 'manual',
            'source_hash' => sha1('legacy-event-candidate'),
            'title' => 'Legacy event candidate',
            'type' => 'other',
            'start_at' => '2026-04-01 18:30:00',
            'max_at' => '2026-04-01 18:30:00',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.start_at', '2026-04-01T18:30:00+00:00')
            ->assertJsonPath('data.max_at', '2026-04-01T18:30:00+00:00')
            ->assertJsonPath('data.time_type', 'start')
            ->assertJsonPath('data.time_precision', 'exact');
    }
}

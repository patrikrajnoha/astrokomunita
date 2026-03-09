<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\User;
use App\Services\Events\EventCandidatePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCandidatePublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_copies_canonical_matching_fields_to_event(): void
    {
        $reviewer = User::factory()->create();
        $candidate = $this->makeCandidate([
            'source_name' => 'imo',
            'source_uid' => 'imo:lyrids:2026',
            'external_id' => 'imo:lyrids:2026',
            'stable_key' => 'imo:lyrids:2026',
            'fingerprint_v2' => hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'),
            'canonical_key' => 'meteor shower|2026-04-22|lyrids lyr',
            'confidence_score' => 1.0,
            'matched_sources' => ['astropixels', 'imo'],
        ]);

        /** @var EventCandidatePublisher $publisher */
        $publisher = app(EventCandidatePublisher::class);
        $event = $publisher->approve($candidate, $reviewer->id);

        $this->assertSame('meteor shower|2026-04-22|lyrids lyr', $event->canonical_key);
        $this->assertSame('1.00', (string) $event->confidence_score);
        $this->assertSame(['astropixels', 'imo'], $event->matched_sources);
    }

    public function test_approve_merges_matching_fields_when_event_already_exists(): void
    {
        $reviewer = User::factory()->create();

        $existingEvent = Event::query()->create([
            'title' => 'Lyrids',
            'description' => 'Existing event',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'end_at' => null,
            'max_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'short' => 'Existing short',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'astropixels:lyrids:2026',
            'source_hash' => hash('sha256', 'existing-event'),
            'fingerprint_v2' => hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'),
            'canonical_key' => 'meteor shower|2026-04-22|lyrids lyr',
            'confidence_score' => 0.7,
            'matched_sources' => ['astropixels'],
        ]);

        $candidate = $this->makeCandidate([
            'source_name' => 'astropixels',
            'source_uid' => 'astropixels:lyrids:2026',
            'external_id' => 'astropixels:lyrids:2026',
            'stable_key' => 'astropixels:lyrids:2026',
            'fingerprint_v2' => hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'),
            'canonical_key' => 'meteor shower|2026-04-22|lyrids lyr',
            'confidence_score' => 1.0,
            'matched_sources' => ['astropixels', 'imo'],
        ]);

        /** @var EventCandidatePublisher $publisher */
        $publisher = app(EventCandidatePublisher::class);
        $published = $publisher->approve($candidate, $reviewer->id);

        $this->assertSame($existingEvent->id, $published->id);
        $published->refresh();
        $this->assertSame(['astropixels', 'imo'], $published->matched_sources);
        $this->assertSame('1.00', (string) $published->confidence_score);
        $this->assertSame('meteor shower|2026-04-22|lyrids lyr', $published->canonical_key);

        $candidate->refresh();
        $this->assertSame(EventCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertSame($existingEvent->id, (int) $candidate->published_event_id);
    }

    public function test_approve_reuses_existing_event_from_other_source_by_canonical_signals(): void
    {
        $reviewer = User::factory()->create();

        $existingEvent = Event::query()->create([
            'title' => 'Lyrids',
            'description' => 'Existing Astropixels event',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'end_at' => null,
            'max_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'short' => 'Lyrids short',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'astropixels:lyrids:2026',
            'source_hash' => hash('sha256', 'existing-astropixels-event'),
            'fingerprint_v2' => hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'),
            'canonical_key' => 'meteor shower|2026-04-22|lyrids lyr',
            'confidence_score' => 0.7,
            'matched_sources' => ['astropixels'],
        ]);

        $candidate = $this->makeCandidate([
            'source_name' => 'imo',
            'source_uid' => 'imo:lyrids:2026',
            'external_id' => 'imo:lyrids:2026',
            'stable_key' => 'imo:lyrids:2026',
            'source_hash' => hash('sha256', 'imo:lyrids:2026'),
            'fingerprint_v2' => hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'),
            'canonical_key' => 'meteor shower|2026-04-22|lyrids lyr',
            'confidence_score' => 1.0,
            'matched_sources' => ['astropixels', 'imo'],
        ]);

        /** @var EventCandidatePublisher $publisher */
        $publisher = app(EventCandidatePublisher::class);
        $published = $publisher->approve($candidate, $reviewer->id);

        $this->assertSame($existingEvent->id, $published->id);
        $this->assertSame(1, Event::query()->count());

        $published->refresh();
        $this->assertSame(['astropixels', 'imo'], $published->matched_sources);
        $this->assertSame('1.00', (string) $published->confidence_score);
        $this->assertSame('meteor shower|2026-04-22|lyrids lyr', $published->canonical_key);
        $this->assertSame(hash('sha256', 'meteor shower|2026-04-22|lyrids lyr'), $published->fingerprint_v2);

        $candidate->refresh();
        $this->assertSame(EventCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertSame($existingEvent->id, (int) $candidate->published_event_id);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeCandidate(array $overrides = []): EventCandidate
    {
        return EventCandidate::query()->create(array_merge([
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'source_uid' => 'candidate-1',
            'external_id' => 'candidate-1',
            'stable_key' => 'candidate-1',
            'source_hash' => hash('sha256', 'candidate-1'),
            'fingerprint_v2' => hash('sha256', 'candidate-1'),
            'title' => 'Lyrids',
            'description' => 'Candidate description',
            'type' => 'meteor_shower',
            'max_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'start_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'end_at' => null,
            'short' => 'Candidate short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ], $overrides));
    }
}

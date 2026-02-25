<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPublicConfidenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_index_includes_public_confidence_payload(): void
    {
        $event = Event::query()->create([
            'title' => 'Lyrids',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'end_at' => null,
            'max_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'short' => 'Peak night',
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'imo:lyrids:2026',
            'source_hash' => hash('sha256', 'imo:lyrids:2026'),
            'confidence_score' => 1.0,
            'matched_sources' => ['imo', 'astropixels'],
        ]);
        EventCandidate::query()->create([
            'source_name' => 'imo',
            'source_hash' => hash('sha256', 'candidate-imo:lyrids:2026'),
            'title' => 'Lyrids candidate',
            'type' => 'meteor_shower',
            'max_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'start_at' => CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $response = $this->getJson('/api/events?year=2026');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $event->id);
        $this->assertNotNull($row);

        $this->assertArrayHasKey('public_confidence', $row);
        $this->assertSame('verified', $row['public_confidence']['level']);
        $this->assertArrayHasKey('label', $row['public_confidence']);
        $this->assertArrayHasKey('score', $row['public_confidence']);
        $this->assertArrayHasKey('sources_count', $row['public_confidence']);
        $this->assertArrayNotHasKey('matched_sources', $row['public_confidence']);
    }
}

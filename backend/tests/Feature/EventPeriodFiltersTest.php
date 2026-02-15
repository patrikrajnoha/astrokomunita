<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPeriodFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_and_week_cannot_be_combined(): void
    {
        $response = $this->getJson('/api/events?year=2026&month=1&week=2');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['month', 'week']);
    }

    public function test_year_and_month_wrapper_filters_expected_range(): void
    {
        $inRange = $this->createPublishedEvent('Month event', CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
        $outRange = $this->createPublishedEvent('Out of month', CarbonImmutable::parse('2026-02-01 12:00:00', 'UTC'));

        $response = $this->getJson('/api/events?year=2026&month=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outRange->id));
    }

    public function test_year_and_week_wrapper_uses_iso_week_range(): void
    {
        $inRange = $this->createPublishedEvent('Week event', CarbonImmutable::parse('2026-01-02 12:00:00', 'UTC'));
        $outRange = $this->createPublishedEvent('Out of week', CarbonImmutable::parse('2026-01-07 12:00:00', 'UTC'));

        $response = $this->getJson('/api/events?year=2026&week=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outRange->id));
    }

    public function test_years_endpoint_returns_bounded_defaults(): void
    {
        $response = $this->getJson('/api/events/years');
        $response->assertOk();
        $response->assertJsonStructure([
            'years',
            'defaultYear',
            'currentYearBounded',
            'minYear',
            'maxYear',
        ]);

        $payload = $response->json();
        $expectedBounded = max((int) $payload['minYear'], min((int) $payload['maxYear'], (int) now()->year));

        $this->assertSame($expectedBounded, (int) $payload['defaultYear']);
        $this->assertSame($expectedBounded, (int) $payload['currentYearBounded']);
    }

    private function createPublishedEvent(string $title, CarbonImmutable $startAtUtc): Event
    {
        $event = Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => $startAtUtc,
            'end_at' => $startAtUtc->addHour(),
            'max_at' => $startAtUtc,
            'visibility' => 1,
            'source_name' => 'test-source',
            'source_uid' => uniqid('uid_', true),
            'source_hash' => uniqid('hash_', true),
        ]);

        EventCandidate::query()->create([
            'source_name' => 'test-source',
            'source_hash' => sha1(uniqid('candidate_hash_', true)),
            'title' => $title . ' candidate',
            'type' => 'other',
            'max_at' => $startAtUtc,
            'start_at' => $startAtUtc,
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        return $event;
    }
}

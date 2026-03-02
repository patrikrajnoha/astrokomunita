<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        $response = $this->getJson('/api/events?scope=all&year=2026&month=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outRange->id));
    }

    public function test_year_and_week_wrapper_uses_iso_week_range(): void
    {
        $inRange = $this->createPublishedEvent('Week event', CarbonImmutable::parse('2026-01-02 12:00:00', 'UTC'));
        $outRange = $this->createPublishedEvent('Out of week', CarbonImmutable::parse('2026-01-07 12:00:00', 'UTC'));

        $response = $this->getJson('/api/events?scope=all&year=2026&week=1');
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

    public function test_period_wrappers_respect_events_display_timezone(): void
    {
        config([
            'app.timezone' => 'UTC',
            'events.timezone' => 'Europe/Bratislava',
        ]);

        $inRange = $this->createPublishedEvent(
            'Timezone boundary event',
            CarbonImmutable::parse('2026-01-01 00:30:00', 'Europe/Bratislava')->utc()
        );
        $outRange = $this->createPublishedEvent(
            'Previous local year',
            CarbonImmutable::parse('2025-12-31 22:30:00', 'UTC')
        );

        $response = $this->getJson('/api/events?scope=all&year=2026&month=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outRange->id));
    }

    public function test_month_and_week_filters_follow_local_day_boundaries(): void
    {
        config([
            'app.timezone' => 'UTC',
            'events.timezone' => 'Europe/Bratislava',
        ]);

        $localFebruaryEvent = $this->createPublishedEvent(
            'UTC previous day, local next day',
            CarbonImmutable::parse('2026-01-31 23:30:00', 'UTC')
        );
        $localJanuaryEvent = $this->createPublishedEvent(
            'UTC and local same day',
            CarbonImmutable::parse('2026-01-31 00:30:00', 'UTC')
        );
        $localWeekTwoEvent = $this->createPublishedEvent(
            'UTC Sunday, local Monday',
            CarbonImmutable::parse('2026-01-04 23:30:00', 'UTC')
        );
        $localWeekOneEvent = $this->createPublishedEvent(
            'UTC and local Sunday',
            CarbonImmutable::parse('2026-01-04 00:30:00', 'UTC')
        );

        $january = $this->getJson('/api/events?scope=all&year=2026&month=1')->assertOk();
        $januaryIds = collect($january->json('data'))->pluck('id');
        $this->assertTrue($januaryIds->contains($localJanuaryEvent->id));
        $this->assertFalse($januaryIds->contains($localFebruaryEvent->id));

        $february = $this->getJson('/api/events?scope=all&year=2026&month=2')->assertOk();
        $februaryIds = collect($february->json('data'))->pluck('id');
        $this->assertTrue($februaryIds->contains($localFebruaryEvent->id));
        $this->assertFalse($februaryIds->contains($localJanuaryEvent->id));

        $weekOne = $this->getJson('/api/events?scope=all&year=2026&week=1')->assertOk();
        $weekOneIds = collect($weekOne->json('data'))->pluck('id');
        $this->assertTrue($weekOneIds->contains($localWeekOneEvent->id));
        $this->assertFalse($weekOneIds->contains($localWeekTwoEvent->id));

        $weekTwo = $this->getJson('/api/events?scope=all&year=2026&week=2')->assertOk();
        $weekTwoIds = collect($weekTwo->json('data'))->pluck('id');
        $this->assertTrue($weekTwoIds->contains($localWeekTwoEvent->id));
        $this->assertFalse($weekTwoIds->contains($localWeekOneEvent->id));
    }

    public function test_filters_fall_back_when_event_date_column_is_unavailable(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_event_date_idx');
            $table->dropColumn('event_date');
        });

        $inRange = $this->createPublishedEvent('Legacy schema event', CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));
        $outRange = $this->createPublishedEvent('Legacy schema out', CarbonImmutable::parse('2026-04-01 12:00:00', 'UTC'));

        $response = $this->getJson('/api/events?scope=all&year=2026&month=3');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outRange->id));
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

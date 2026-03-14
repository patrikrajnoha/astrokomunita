<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NextMeteorWidgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_returns_nearest_upcoming_meteor_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->createManualEvent('Lunar eclipse', 'eclipse-1', 'eclipse_lunar', now()->addDay());
        $meteor = $this->createManualEvent('Perzeidy', 'meteor-1', 'meteor_shower', now()->addDays(3));
        $this->createManualEvent('Random fireball alert', 'meteor-2', 'meteors', now()->addDays(10));

        $this->getJson('/api/events/widget/next-meteor-shower')
            ->assertOk()
            ->assertJsonPath('data.id', $meteor->id)
            ->assertJsonPath('data.type', 'meteor_shower')
            ->assertJsonPath('source.label', 'Databaza udalosti');
    }

    public function test_endpoint_returns_null_when_no_upcoming_meteor_event_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->createManualEvent('Past meteor shower', 'meteor-past', 'meteor_shower', now()->subDay());

        $this->getJson('/api/events/widget/next-meteor-shower')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('source.label', 'Databaza udalosti');
    }

    public function test_endpoint_cache_is_invalidated_when_future_meteor_event_is_created(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->getJson('/api/events/widget/next-meteor-shower')
            ->assertOk()
            ->assertJsonPath('data', null);

        $meteor = $this->createManualEvent('Lyridy', 'meteor-future', 'meteor_shower', now()->addDays(5));

        $this->getJson('/api/events/widget/next-meteor-shower')
            ->assertOk()
            ->assertJsonPath('data.id', $meteor->id)
            ->assertJsonPath('data.title', 'Lyridy');
    }

    private function createManualEvent(string $title, string $sourceUid, string $type, Carbon $startAt, int $visibility = 1): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => $type,
            'start_at' => $startAt,
            'max_at' => $startAt,
            'visibility' => $visibility,
            'source_name' => 'manual',
            'source_uid' => $sourceUid,
            'source_hash' => $sourceUid,
        ]);
    }
}

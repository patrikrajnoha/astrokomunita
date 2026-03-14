<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UpcomingEventsWidgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_returns_four_nearest_future_events_sorted_and_with_minimal_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->createManualEvent('Past event', 'past-1', now()->subHour());
        $future3 = $this->createManualEvent('Future 3', 'future-3', now()->addHours(3));
        $futureA = $this->createManualEvent('Future 1A', 'future-1a', now()->addHour());
        $futureB = $this->createManualEvent('Future 1B', 'future-1b', now()->addHour());
        $future2 = $this->createManualEvent('Future 2', 'future-2', now()->addHours(2));
        $this->createManualEvent('Future 4', 'future-4', now()->addHours(4));
        $this->createManualEvent('Hidden future', 'hidden-1', now()->addMinutes(10), 0);

        $response = $this->getJson('/api/events/widget/upcoming')
            ->assertOk()
            ->assertJsonStructure([
                'items',
                'source',
                'generated_at',
            ]);

        $items = $response->json('items');

        $this->assertCount(4, $items);
        $this->assertSame(
            [$futureA->id, $futureB->id, $future2->id, $future3->id],
            array_column($items, 'id')
        );

        $topLevelKeys = array_keys($response->json());
        sort($topLevelKeys);
        $this->assertSame(['generated_at', 'items', 'source'], $topLevelKeys);
        $this->assertSame('Databaza udalosti', $response->json('source.label'));

        $itemKeys = array_keys($items[0]);
        sort($itemKeys);
        $this->assertSame(['id', 'slug', 'start_at', 'title'], $itemKeys);
    }

    public function test_endpoint_response_is_cached_until_events_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $event = $this->createManualEvent('Original title', 'cache-1', now()->addHour());
        $cacheKey = 'widget:upcoming-events:v1';

        $first = $this->getJson('/api/events/widget/upcoming')->assertOk();
        $second = $this->getJson('/api/events/widget/upcoming')->assertOk();

        $this->assertSame('Original title', $second->json('items.0.title'));

        $event->update([
            'title' => 'Changed title',
        ]);

        $third = $this->getJson('/api/events/widget/upcoming')->assertOk();

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertSame('Changed title', $third->json('items.0.title'));
    }

    private function createManualEvent(string $title, string $sourceUid, Carbon $startAt, int $visibility = 1): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'meteor_shower',
            'start_at' => $startAt,
            'max_at' => $startAt,
            'visibility' => $visibility,
            'source_name' => 'manual',
            'source_uid' => $sourceUid,
            'source_hash' => $sourceUid,
        ]);
    }
}

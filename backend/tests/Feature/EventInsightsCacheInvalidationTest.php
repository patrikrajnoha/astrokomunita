<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Services\Events\EventInsightsCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EventInsightsCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_at_change_invalidates_insights_cache(): void
    {
        $event = $this->createEvent('evt-insights-start-at');
        $insightsCache = app(EventInsightsCacheService::class);

        $insightsCache->put($event, 'zaujimavost', 'pozorovanie');
        $cacheKey = $insightsCache->key((int) $event->id);

        $this->assertNotNull(Cache::get($cacheKey));

        $event->update([
            'start_at' => CarbonImmutable::parse('2026-03-08 20:30:00', 'UTC'),
        ]);

        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_unrelated_field_change_keeps_insights_cache(): void
    {
        $event = $this->createEvent('evt-insights-unrelated');
        $insightsCache = app(EventInsightsCacheService::class);

        $insightsCache->put($event, 'zaujimavost', 'pozorovanie');
        $cacheKey = $insightsCache->key((int) $event->id);
        $cachedBefore = Cache::get($cacheKey);

        $this->assertIsArray($cachedBefore);

        $event->update([
            'description' => 'Toto je zmena, ktora nema invalidovat insights cache.',
        ]);

        $cachedAfter = Cache::get($cacheKey);
        $this->assertIsArray($cachedAfter);
        $this->assertSame(
            (string) ($cachedBefore['factual_hash'] ?? ''),
            (string) ($cachedAfter['factual_hash'] ?? '')
        );
    }

    private function createEvent(string $sourceUid): Event
    {
        return Event::query()->create([
            'title' => 'Mesiac v perigeu: 363000 km',
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-03-08 20:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-03-08 21:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-03-08 20:15:00', 'UTC'),
            'visibility' => 1,
            'region_scope' => 'global',
            'source_name' => 'manual',
            'source_uid' => $sourceUid,
            'source_hash' => hash('sha256', $sourceUid),
        ]);
    }
}


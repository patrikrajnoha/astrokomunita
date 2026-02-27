<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use App\Services\FeaturedEventsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeaturedEventsResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_selection_has_priority_over_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 10:00:00', 'UTC'));

        $adminEvent = $this->createEvent('Admin event', '2026-02-20 20:00:00', 'other', 'manual');
        $this->createEvent('Fallback major', '2026-02-11 22:00:00', 'eclipse', 'astropixels');

        MonthlyFeaturedEvent::query()->create([
            'event_id' => $adminEvent->id,
            'month_key' => '2026-02',
            'position' => 0,
            'is_active' => true,
        ]);

        $resolved = app(FeaturedEventsResolver::class)->resolveForMonth('2026-02');

        $this->assertSame('admin', $resolved['mode']);
        $this->assertNull($resolved['fallback_reason']);
        $this->assertCount(1, $resolved['events']);
        $this->assertSame($adminEvent->id, $resolved['events'][0]['id']);
    }

    public function test_fallback_is_used_when_admin_selection_is_missing_or_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 10:00:00', 'UTC'));

        $inactiveAdmin = $this->createEvent('Inactive admin', '2026-02-05 12:00:00');
        MonthlyFeaturedEvent::query()->create([
            'event_id' => $inactiveAdmin->id,
            'month_key' => '2026-02',
            'position' => 0,
            'is_active' => false,
        ]);

        foreach (range(1, 6) as $index) {
            $this->createEvent("Fallback {$index}", sprintf('2026-02-%02d 18:00:00', 10 + $index));
        }

        $resolved = app(FeaturedEventsResolver::class)->resolveForMonth('2026-02');

        $this->assertSame('fallback', $resolved['mode']);
        $this->assertSame('no_admin_selection', $resolved['fallback_reason']);
        $this->assertCount(FeaturedEventsResolver::DEFAULT_FALLBACK_LIMIT, $resolved['events']);
    }

    public function test_fallback_order_is_deterministic_for_same_score(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00', 'UTC'));

        $firstIdTie = $this->createEvent('Tie A', '2026-02-10 10:00:00', 'other', 'manual');
        $secondIdTie = $this->createEvent('Tie B', '2026-02-10 10:00:00', 'other', 'manual');
        $laterStart = $this->createEvent('Tie C', '2026-02-10 11:00:00', 'other', 'manual');

        $resolved = app(FeaturedEventsResolver::class)->resolveForMonth('2026-02');

        $this->assertSame('fallback', $resolved['mode']);
        $this->assertSame($firstIdTie->id, $resolved['events'][0]['id']);
        $this->assertSame($secondIdTie->id, $resolved['events'][1]['id']);
        $this->assertSame($laterStart->id, $resolved['events'][2]['id']);
    }

    private function createEvent(string $title, string $startAt, string $type = 'other', string $sourceName = 'manual'): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => $type,
            'start_at' => Carbon::parse($startAt, 'UTC'),
            'end_at' => null,
            'visibility' => 1,
            'source_name' => $sourceName,
            'source_uid' => uniqid($sourceName . '-', true),
        ]);
    }
}

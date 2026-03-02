<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventScopeFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_scope_returns_only_future_events(): void
    {
        config(['app.timezone' => 'Europe/Bratislava']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', config('app.timezone')));

        $past = $this->createPublishedEvent('Past event', now()->subHour());
        $future = $this->createPublishedEvent('Future event', now()->addHour());

        $response = $this->getJson('/api/events')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($past->id, $ids);
        $this->assertSame([$future->id], $ids);
    }

    public function test_past_scope_returns_only_past_events(): void
    {
        config(['app.timezone' => 'Europe/Bratislava']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', config('app.timezone')));

        $recentPast = $this->createPublishedEvent('Recent past', now()->subHour());
        $olderPast = $this->createPublishedEvent('Older past', now()->subDays(2));
        $future = $this->createPublishedEvent('Future event', now()->addHour());

        $response = $this->getJson('/api/events?scope=past')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$recentPast->id, $olderPast->id], $ids);
        $this->assertNotContains($future->id, $ids);
    }

    public function test_all_scope_returns_future_and_past_events(): void
    {
        config(['app.timezone' => 'Europe/Bratislava']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', config('app.timezone')));

        $futureSoon = $this->createPublishedEvent('Future soon', now()->addHour());
        $futureLater = $this->createPublishedEvent('Future later', now()->addDays(2));
        $recentPast = $this->createPublishedEvent('Recent past', now()->subHour());
        $olderPast = $this->createPublishedEvent('Older past', now()->subDays(2));

        $response = $this->getJson('/api/events?scope=all')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame(
            [$futureSoon->id, $futureLater->id, $recentPast->id, $olderPast->id],
            $ids
        );
    }

    public function test_scope_keeps_existing_type_region_and_text_filters_working(): void
    {
        config(['app.timezone' => 'Europe/Bratislava']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 12:00:00', config('app.timezone')));

        $match = $this->createPublishedEvent(
            'Perzeidy Slovensko',
            now()->addDay(),
            type: 'meteors',
            region: 'sk'
        );
        $this->createPublishedEvent('Perzeidy Europa', now()->addDay(), type: 'meteors', region: 'eu');
        $this->createPublishedEvent('Zatmenie Slovensko', now()->addDay(), type: 'eclipse', region: 'sk');

        $response = $this->getJson('/api/events?scope=future&type=meteors&region=sk&q=Perzeidy')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$match->id], $ids);
    }

    private function createPublishedEvent(
        string $title,
        Carbon $startAt,
        string $type = 'other',
        string $region = 'global',
        int $visibility = 1,
    ): Event {
        return Event::query()->create([
            'title' => $title,
            'type' => $type,
            'region_scope' => $region,
            'start_at' => $startAt->copy()->utc(),
            'end_at' => $startAt->copy()->utc()->addHour(),
            'max_at' => $startAt->copy()->utc(),
            'visibility' => $visibility,
            'source_name' => 'manual',
            'source_uid' => uniqid('event_', true),
            'source_hash' => sha1($title . '|' . $startAt->toIso8601String()),
            'description' => $title,
        ]);
    }
}

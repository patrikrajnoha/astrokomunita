<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NextEclipseWidgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_returns_nearest_upcoming_eclipse_with_minimal_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->createManualEvent('Meteor event', 'meteor-1', 'meteor_shower', now()->addHour());
        $this->createManualEvent('Past lunar eclipse', 'eclipse-past', 'eclipse_lunar', now()->subDay());
        $futureSolar = $this->createManualEvent('Solar Eclipse', 'eclipse-solar', 'eclipse_solar', now()->addDays(10));
        $this->createManualEvent('Later lunar eclipse', 'eclipse-lunar', 'eclipse_lunar', now()->addDays(30));

        $response = $this->getJson('/api/events/widget/next-eclipse')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'source',
                'generated_at',
            ])
            ->assertJsonPath('data.id', $futureSolar->id)
            ->assertJsonPath('data.type', 'eclipse_solar')
            ->assertJsonPath('source.label', 'Databáza udalostí');

        $keys = array_keys($response->json('data'));
        sort($keys);
        $this->assertSame(['end_at', 'id', 'max_at', 'source', 'start_at', 'title', 'type', 'updated_at'], $keys);
    }

    public function test_endpoint_returns_null_when_no_upcoming_eclipse_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16 12:00:00', config('app.timezone')));
        Cache::flush();

        $this->createManualEvent('Past lunar eclipse', 'eclipse-past', 'eclipse_lunar', now()->subDay());

        $this->getJson('/api/events/widget/next-eclipse')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('source.label', 'Databáza udalostí');
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

<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublishedGateConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_published_gate_is_consistent_across_public_event_endpoints(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-22 09:00:00', 'UTC'));

        $eventA = $this->createEvent(
            title: 'Approved candidate event',
            sourceName: 'astropixels',
            sourceUid: 'approved-candidate',
            visibility: 1,
            startAt: now()->addDays(1)
        );
        $this->createCandidate($eventA, EventCandidate::STATUS_APPROVED);

        $eventB = $this->createEvent(
            title: 'Hidden event',
            sourceName: 'manual',
            sourceUid: 'hidden-manual',
            visibility: 0,
            startAt: now()->addDays(2)
        );

        $eventC = $this->createEvent(
            title: 'Pending candidate event',
            sourceName: 'astropixels',
            sourceUid: 'pending-candidate',
            visibility: 1,
            startAt: now()->addDays(3)
        );
        $this->createCandidate($eventC, EventCandidate::STATUS_PENDING);

        $eventD = $this->createEvent(
            title: 'Manual event',
            sourceName: 'manual',
            sourceUid: 'manual-event',
            visibility: 1,
            startAt: now()->addDays(4)
        );

        $indexResponse = $this->getJson('/api/events')->assertOk();
        $indexIds = collect($indexResponse->json('data'))->pluck('id')->all();

        $this->assertContains($eventA->id, $indexIds);
        $this->assertContains($eventD->id, $indexIds);
        $this->assertNotContains($eventB->id, $indexIds);
        $this->assertNotContains($eventC->id, $indexIds);

        $widgetResponse = $this->getJson('/api/events/widget/upcoming')->assertOk();
        $widgetIds = collect($widgetResponse->json('items'))->pluck('id')->all();

        $this->assertSame([$eventA->id, $eventD->id], $widgetIds);
        $this->assertNotContains($eventB->id, $widgetIds);
        $this->assertNotContains($eventC->id, $widgetIds);

        $this->getJson('/api/events/' . $eventA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $eventA->id);
        $this->getJson('/api/events/' . $eventD->id)
            ->assertOk()
            ->assertJsonPath('data.id', $eventD->id);
        $this->getJson('/api/events/' . $eventB->id)->assertNotFound();
        $this->getJson('/api/events/' . $eventC->id)->assertNotFound();

        $this->get('/api/events/' . $eventA->id . '/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->get('/api/events/' . $eventD->id . '/calendar.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->get('/api/events/' . $eventB->id . '/calendar.ics')->assertNotFound();
        $this->get('/api/events/' . $eventC->id . '/calendar.ics')->assertNotFound();
    }

    private function createEvent(
        string $title,
        string $sourceName,
        string $sourceUid,
        int $visibility,
        Carbon $startAt,
    ): Event {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => $startAt->copy()->utc(),
            'end_at' => $startAt->copy()->utc()->addHour(),
            'max_at' => $startAt->copy()->utc(),
            'visibility' => $visibility,
            'source_name' => $sourceName,
            'source_uid' => $sourceUid,
            'source_hash' => sha1($sourceName . '|' . $sourceUid),
            'description' => 'Fixture event description',
        ]);
    }

    private function createCandidate(Event $event, string $status): void
    {
        EventCandidate::query()->create([
            'source_name' => (string) $event->source_name,
            'source_uid' => (string) $event->source_uid,
            'source_hash' => sha1('candidate|' . $event->id . '|' . $status),
            'title' => (string) $event->title . ' candidate',
            'type' => (string) $event->type,
            'max_at' => $event->max_at,
            'start_at' => $event->start_at,
            'status' => $status,
            'published_event_id' => $event->id,
        ]);
    }
}

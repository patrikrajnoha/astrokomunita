<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventCalendarExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_event_calendar_endpoint_returns_ics_content(): void
    {
        $event = $this->createEvent('Lunar Eclipse', '2026-02-12 20:00:00');

        $response = $this->get('/api/events/' . $event->id . '/calendar.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="astrokomunita-event-' . $event->id . '.ics"'
        );

        $content = (string) $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('DTSTART:', $content);
        $this->assertStringContainsString('SUMMARY:Lunar Eclipse', $content);
        $uidHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (!is_string($uidHost) || $uidHost === '' || $uidHost === 'localhost') {
            $uidHost = 'astrokomunita';
        }
        $this->assertStringContainsString('UID:event-' . $event->id . '@' . $uidHost, $content);
        $this->assertStringContainsString('LOCATION:Slovensko', $content);
    }

    public function test_featured_bundle_calendar_endpoint_returns_multiple_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 10:00:00', 'UTC'));

        try {
            $eventA = $this->createEvent('Event A', '2026-02-15 20:00:00');
            $eventB = $this->createEvent('Event B', '2026-02-20 20:00:00');

            MonthlyFeaturedEvent::query()->create([
                'event_id' => $eventA->id,
                'month_key' => '2026-02',
                'position' => 0,
                'is_active' => true,
            ]);
            MonthlyFeaturedEvent::query()->create([
                'event_id' => $eventB->id,
                'month_key' => '2026-02',
                'position' => 1,
                'is_active' => true,
            ]);

            $response = $this->get('/api/featured-events/2026-02/calendar.ics');

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

            $content = (string) $response->getContent();
            $this->assertSame(2, substr_count($content, 'BEGIN:VEVENT'));
            $this->assertStringContainsString('SUMMARY:Event A', $content);
            $this->assertStringContainsString('SUMMARY:Event B', $content);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createEvent(string $title, string $startAt): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => Carbon::parse($startAt, 'UTC'),
            'end_at' => Carbon::parse($startAt, 'UTC')->addHour(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('manual-', true),
            'description' => 'Observation details',
        ]);
    }
}

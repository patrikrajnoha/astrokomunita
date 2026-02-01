<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class EventCalendarController extends Controller
{
    /**
     * GET /api/events/{event}/ics
     */
    public function show(int $id): Response
    {
        $event = Event::query()
            ->where('visibility', 1)
            ->published()
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('event_candidates')
                    ->whereColumn('event_candidates.published_event_id', 'events.id')
                    ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
            })
            ->findOrFail($id);

        $start = $event->start_at ?? $event->max_at;
        if (!$start) {
            abort(404);
        }

        $allDay = (bool) ($event->all_day ?? false);
        $end = $event->end_at;
        if (!$end) {
            $end = $allDay ? $start->copy()->addDay() : $start->copy()->addHour();
        }

        $ics = $this->buildIcs($event, $start, $end, $allDay);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-' . $event->id . '.ics"',
        ]);
    }

    private function buildIcs(Event $event, $start, $end, bool $allDay): string
    {
        $uidHost = $this->uidHost();
        $uid = "event-{$event->id}@{$uidHost}";
        $dtstamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');

        $summary = $this->escapeText($event->title ?? 'Udalosť');
        $description = $this->escapeText($event->description ?: ($event->short ?: ''));
        $url = $this->eventUrl($event->id);

        if ($allDay) {
            $dtStart = $start->format('Ymd');
            $dtEnd = $end->format('Ymd');
            $dtStartLine = "DTSTART;VALUE=DATE:{$dtStart}";
            $dtEndLine = "DTEND;VALUE=DATE:{$dtEnd}";
        } else {
            $dtStart = $start->clone()->utc()->format('Ymd\THis\Z');
            $dtEnd = $end->clone()->utc()->format('Ymd\THis\Z');
            $dtStartLine = "DTSTART:{$dtStart}";
            $dtEndLine = "DTEND:{$dtEnd}";
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Nebesky sprievodca//Calendar 1.0//SK',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtstamp}",
            $dtStartLine,
            $dtEndLine,
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            "URL:{$url}",
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    private function eventUrl(int $id): string
    {
        $base = rtrim(config('app.url'), '/');
        if (!$base) {
            return "http://localhost/events/{$id}";
        }
        return "{$base}/events/{$id}";
    }

    private function uidHost(): string
    {
        $url = config('app.url') ?: 'http://localhost';
        $host = parse_url($url, PHP_URL_HOST);
        return $host ?: 'localhost';
    }

    private function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
        return $value;
    }
}

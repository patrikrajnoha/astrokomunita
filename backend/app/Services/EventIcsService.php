<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EventIcsService
{
    public function __construct(
        private readonly EventCalendarLinksService $calendarLinks,
    ) {
    }

    public function buildSingleEventIcs(Event $event): string
    {
        return $this->buildBundleIcs(collect([$event]), 'Event');
    }

    /**
     * @param Collection<int, Event> $events
     */
    public function buildBundleIcs(Collection $events, string $calendarName = 'Featured events'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Nebesky sprievodca//Calendar 1.0//SK',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calendarName),
        ];

        $dtStamp = CarbonImmutable::now('UTC')->format('Ymd\\THis\\Z');
        $uidHost = $this->uidHost();

        foreach ($events as $event) {
            $window = $this->calendarLinks->eventWindow($event);
            if ($window === null) {
                continue;
            }

            $uid = sprintf(
                'event-%d-%s@%s',
                (int) $event->id,
                $window['start']->utc()->format('YmdHis'),
                $uidHost
            );

            $url = $this->calendarLinks->eventPublicUrl($event);
            $description = trim((string) ($event->description ?: $event->short ?: ''));
            if ($description !== '') {
                $description .= "\n\n" . $url;
            } else {
                $description = $url;
            }

            $eventLines = [
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . $dtStamp,
            ];

            if ($window['all_day']) {
                $eventLines[] = 'DTSTART;VALUE=DATE:' . $window['start']->format('Ymd');
                $eventLines[] = 'DTEND;VALUE=DATE:' . $window['end']->format('Ymd');
            } else {
                $eventLines[] = 'DTSTART:' . $window['start']->utc()->format('Ymd\\THis\\Z');
                $eventLines[] = 'DTEND:' . $window['end']->utc()->format('Ymd\\THis\\Z');
            }

            $eventLines[] = 'SUMMARY:' . $this->escapeText((string) ($event->title ?? 'Udalost'));
            $eventLines[] = 'DESCRIPTION:' . $this->escapeText($description);

            $location = trim((string) ($event->location ?? ''));
            if ($location !== '') {
                $eventLines[] = 'LOCATION:' . $this->escapeText($location);
            }

            $eventLines[] = 'URL:' . $url;
            $eventLines[] = 'END:VEVENT';

            $lines = array_merge($lines, $eventLines);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function uidHost(): string
    {
        $url = config('app.url') ?: 'http://localhost';
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
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

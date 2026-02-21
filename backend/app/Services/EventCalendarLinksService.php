<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class EventCalendarLinksService
{
    public function eventIcsUrl(Event|int $event): string
    {
        $eventId = $event instanceof Event ? (int) $event->id : (int) $event;
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');

        return $base . '/api/events/' . $eventId . '/calendar.ics';
    }

    public function featuredBundleIcsUrl(string $monthKey): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');

        return $base . '/api/featured-events/' . $monthKey . '/calendar.ics';
    }

    public function eventPublicUrl(Event|int $event): string
    {
        $eventId = $event instanceof Event ? (int) $event->id : (int) $event;
        $base = rtrim((string) config('newsletter.frontend_base_url', config('app.url', 'http://localhost')), '/');

        return $base . '/events/' . $eventId;
    }

    public function googleCalendarUrl(Event $event): string
    {
        $window = $this->eventWindow($event);

        $params = [
            'action' => 'TEMPLATE',
            'text' => (string) ($event->title ?? 'Event'),
            'details' => $this->googleDetails($event),
            'sprop' => 'website:' . $this->eventPublicUrl($event),
        ];

        if ($window !== null) {
            if ($window['all_day']) {
                $params['dates'] = $window['start']->format('Ymd') . '/' . $window['end']->format('Ymd');
            } else {
                $params['dates'] = $window['start']->utc()->format('Ymd\\THis\\Z') . '/' . $window['end']->utc()->format('Ymd\\THis\\Z');
            }
        }

        $location = trim((string) ($event->location ?? ''));
        if ($location !== '') {
            $params['location'] = $location;
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{start:CarbonImmutable,end:CarbonImmutable,all_day:bool}|null
     */
    public function eventWindow(Event $event): ?array
    {
        $start = $event->start_at ?? $event->max_at;
        if (! $start) {
            return null;
        }

        $startImmutable = CarbonImmutable::instance($start);
        $allDay = (bool) ($event->all_day ?? false);

        $end = $event->end_at;
        if (! $end) {
            $end = $allDay ? $startImmutable->addDay() : $startImmutable->addHour();
        }

        return [
            'start' => $startImmutable,
            'end' => $end instanceof CarbonImmutable ? $end : CarbonImmutable::instance($end),
            'all_day' => $allDay,
        ];
    }

    private function googleDetails(Event $event): string
    {
        $text = trim((string) ($event->description ?: $event->short ?: ''));
        $text = Str::limit(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '', 1200, '...');

        $parts = array_values(array_filter([
            $text,
            $this->eventPublicUrl($event),
        ], static fn (string $value): bool => trim($value) !== ''));

        return implode("\n\n", $parts);
    }
}

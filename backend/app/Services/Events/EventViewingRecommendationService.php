<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Support\EventTime;
use Illuminate\Support\Str;

class EventViewingRecommendationService
{
    /**
     * @return array{label:?string,start_at:?string,end_at:?string}
     */
    public function forEvent(Event $event): array
    {
        $timezone = (string) config('events.timezone', config('app.timezone', 'UTC'));
        $start = EventTime::toUtcCarbon($event->start_at) ?? EventTime::toUtcCarbon($event->max_at);
        $end = EventTime::toUtcCarbon($event->end_at);
        $precision = EventTime::normalizePrecision(
            $event->time_precision,
            $event->start_at,
            $event->max_at,
            $event->source_name
        );

        if ($start !== null && $precision !== EventTime::PRECISION_UNKNOWN) {
            $localStart = $start->setTimezone($timezone);
            $label = 'Odporúčaný čas okolo '.$localStart->format('H:i');
            $recommendedEnd = null;

            if ($end !== null) {
                $localEnd = $end->setTimezone($timezone);
                if ($localEnd->gt($localStart) && $localStart->toDateString() === $localEnd->toDateString()) {
                    $label = sprintf(
                        'Odporúčané okno %s - %s',
                        $localStart->format('H:i'),
                        $localEnd->format('H:i')
                    );
                    $recommendedEnd = EventTime::serializeUtc($end);
                }
            }

            return [
                'label' => $label,
                'start_at' => EventTime::serializeUtc($start),
                'end_at' => $recommendedEnd,
            ];
        }

        $fallbackLabel = $this->resolveTextFallback($event);

        return [
            'label' => $fallbackLabel,
            'start_at' => null,
            'end_at' => null,
        ];
    }

    private function resolveTextFallback(Event $event): ?string
    {
        $text = Str::of(implode(' ', [
            (string) ($event->title ?? ''),
            (string) ($event->short ?? ''),
            (string) ($event->description ?? ''),
        ]))
            ->ascii()
            ->lower()
            ->value();

        if ($this->containsAny($text, ['po zotmeni', 'after dark', 'after dusk', 'v noci', 'nocne'])) {
            return 'Najlepšie po zotmení';
        }

        if ($event->type === 'meteor_shower') {
            return 'Najlepšie po zotmení';
        }

        if ($this->containsAny($text, ['vecer', 'vecerne', 'evening'])) {
            return 'Najlepšie večer';
        }

        if ($this->containsAny($text, ['nad ranom', 'pred svitanim', 'rano', 'dawn'])) {
            return 'Najlepšie nad ránom';
        }

        return null;
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}

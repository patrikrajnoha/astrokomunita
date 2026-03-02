<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;

class EventViewingForecastService
{
    private const DEFAULT_DURATION_HOURS = 4;
    private const DEFAULT_FALLBACK_END_HOUR = 2;

    public function __construct(
        private readonly SunMoonProvider $sunMoonProvider,
        private readonly WeatherProvider $weatherProvider
    ) {
    }

    /**
     * @return array{
     *   viewing_window:?array{start_at:string,end_at:string},
     *   summary:?array{
     *     clouds_pct:?int,
     *     temp_c:?float,
     *     wind_ms:?float,
     *     humidity_pct:?int,
     *     precip_pct:?int,
     *     rating:string,
     *     label_sk:string
     *   }
     * }
     */
    public function build(Event $event, float $lat, float $lon, string $tz): array
    {
        $window = $this->resolveViewingWindow($event, $lat, $lon, $tz);
        if ($window === null) {
            return [
                'viewing_window' => null,
                'summary' => null,
            ];
        }

        $hourlyForecast = $this->weatherProvider->hourlyForecast(
            $lat,
            $lon,
            $window['start_at']->toDateString(),
            $window['end_at']->toDateString(),
            $tz
        );

        return [
            'viewing_window' => [
                'start_at' => $window['start_at']->toIso8601String(),
                'end_at' => $window['end_at']->toIso8601String(),
            ],
            'summary' => $this->aggregateForecastSummary(
                $hourlyForecast,
                $window['start_at'],
                $window['end_at']
            ),
        ];
    }

    /**
     * @return array{start_at:CarbonImmutable,end_at:CarbonImmutable}|null
     */
    private function resolveViewingWindow(Event $event, float $lat, float $lon, string $tz): ?array
    {
        $eventStart = $this->toCarbonImmutable($event->start_at);
        $eventEnd = $this->toCarbonImmutable($event->end_at);
        $anchor = $eventStart ?? $this->toCarbonImmutable($event->max_at) ?? $eventEnd;

        if (!$anchor) {
            return null;
        }

        $anchorLocal = $anchor->setTimezone($tz);
        $eventStartLocal = $eventStart?->setTimezone($tz);
        $eventEndLocal = $eventEnd?->setTimezone($tz);

        $currentDate = $anchorLocal->toDateString();
        $previousDate = $anchorLocal->subDay()->toDateString();
        $nextDate = $anchorLocal->addDay()->toDateString();

        $previousSun = $this->sunMoonProvider->get($lat, $lon, $previousDate, $tz);
        $currentSun = $this->sunMoonProvider->get($lat, $lon, $currentDate, $tz);
        $nextSun = $this->sunMoonProvider->get($lat, $lon, $nextDate, $tz);

        $currentMorningEnd = $this->combineLocalDateTime($currentDate, $currentSun['civil_twilight_begin'] ?? null, $tz);
        $previousNightStart = $this->combineLocalDateTime($previousDate, $previousSun['civil_twilight_end'] ?? null, $tz);
        $upcomingNightStart = $this->combineLocalDateTime($currentDate, $currentSun['civil_twilight_end'] ?? null, $tz);
        $upcomingNightEnd = $this->combineLocalDateTime($nextDate, $nextSun['civil_twilight_begin'] ?? null, $tz);

        $usePreviousNight = $currentMorningEnd !== null && $anchorLocal->lt($currentMorningEnd);
        $nightStart = $usePreviousNight ? $previousNightStart : $upcomingNightStart;
        $nightEnd = $usePreviousNight ? $currentMorningEnd : $upcomingNightEnd;

        if ($nightStart === null) {
            $nightStart = $eventStartLocal ?? $anchorLocal;
        }

        $start = $nightStart;
        if ($eventStartLocal !== null && $eventStartLocal->gt($start)) {
            $start = $eventStartLocal;
        }

        if ($eventEndLocal !== null && $eventEndLocal->lte($start)) {
            return null;
        }

        if ($nightEnd !== null && $start->gte($nightEnd)) {
            return null;
        }

        if ($eventEndLocal !== null) {
            $end = $eventEndLocal;
        } elseif (
            $eventStartLocal === null
            && $anchorLocal->gte($nightStart)
            && ($nightEnd === null || $anchorLocal->lt($nightEnd))
        ) {
            $start = $anchorLocal->subHours(intdiv(self::DEFAULT_DURATION_HOURS, 2));
            if ($start->lt($nightStart)) {
                $start = $nightStart;
            }

            $end = $start->addHours(self::DEFAULT_DURATION_HOURS);
        } else {
            $fallbackEnd = $start->addHours(self::DEFAULT_DURATION_HOURS);
            $twoAmCap = $nightStart->addDay()->setTime(self::DEFAULT_FALLBACK_END_HOUR, 0);

            if ($twoAmCap->gt($start) && $twoAmCap->lt($fallbackEnd)) {
                $fallbackEnd = $twoAmCap;
            }

            $end = $fallbackEnd;
        }

        if ($nightEnd !== null && $end->gt($nightEnd)) {
            $end = $nightEnd;
        }

        if ($end->lte($start)) {
            return null;
        }

        return [
            'start_at' => $start,
            'end_at' => $end,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $hourlyForecast
     * @return array{
     *   clouds_pct:?int,
     *   temp_c:?float,
     *   wind_ms:?float,
     *   humidity_pct:?int,
     *   precip_pct:?int,
     *   rating:string,
     *   label_sk:string
     * }|null
     */
    private function aggregateForecastSummary(array $hourlyForecast, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $rangeStart = $start->startOfHour();
        $rangeEnd = $end->addHour()->startOfHour();

        $selected = array_values(array_filter($hourlyForecast, function (array $point) use ($rangeStart, $rangeEnd): bool {
            $pointAt = $this->parseIsoDateTime($point['at'] ?? null);
            if (!$pointAt) {
                return false;
            }

            return $pointAt->gte($rangeStart) && $pointAt->lt($rangeEnd);
        }));

        if ($selected === []) {
            return null;
        }

        $clouds = $this->maxInt($selected, 'cloud_cover_pct');
        $temperature = $this->averageFloat($selected, 'temperature_c');
        $windKmh = $this->averageFloat($selected, 'wind_speed_kmh');
        $humidity = $this->averageInt($selected, 'humidity_pct');
        $precipitation = $this->maxInt($selected, 'precipitation_probability_pct');
        $windMs = $windKmh !== null ? round($windKmh / 3.6, 1) : null;
        $rating = $this->resolveRating($clouds, $precipitation, $windMs);

        return [
            'clouds_pct' => $clouds,
            'temp_c' => $temperature,
            'wind_ms' => $windMs,
            'humidity_pct' => $humidity,
            'precip_pct' => $precipitation,
            'rating' => $rating['rating'],
            'label_sk' => $rating['label_sk'],
        ];
    }

    /**
     * @return array{rating:string,label_sk:string}
     */
    private function resolveRating(?int $clouds, ?int $precipitation, ?float $windMs): array
    {
        if ($clouds === null || $precipitation === null || $windMs === null) {
            return [
                'rating' => 'avg',
                'label_sk' => 'Priemerne',
            ];
        }

        if ($clouds <= 25 && $precipitation <= 20 && $windMs <= 6.0) {
            return [
                'rating' => 'good',
                'label_sk' => 'Dobre',
            ];
        }

        if ($clouds <= 60 && $precipitation <= 40 && $windMs <= 10.0) {
            return [
                'rating' => 'avg',
                'label_sk' => 'Priemerne',
            ];
        }

        return [
            'rating' => 'bad',
            'label_sk' => 'Zle',
        ];
    }

    private function combineLocalDateTime(string $date, mixed $time, string $tz): ?CarbonImmutable
    {
        if (!is_string($time) || !preg_match('/^\d{2}:\d{2}$/', trim($time))) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseIsoDateTime(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toCarbonImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function maxInt(array $rows, string $key): ?int
    {
        $values = array_values(array_filter(array_map(static function (array $row) use ($key): ?int {
            return isset($row[$key]) && is_numeric($row[$key]) ? (int) round((float) $row[$key]) : null;
        }, $rows), static fn (?int $value): bool => $value !== null));

        if ($values === []) {
            return null;
        }

        return max($values);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function averageInt(array $rows, string $key): ?int
    {
        $average = $this->averageFloat($rows, $key);

        return $average === null ? null : (int) round($average);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function averageFloat(array $rows, string $key): ?float
    {
        $values = array_values(array_filter(array_map(static function (array $row) use ($key): ?float {
            return isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : null;
        }, $rows), static fn (?float $value): bool => $value !== null));

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 1);
    }
}

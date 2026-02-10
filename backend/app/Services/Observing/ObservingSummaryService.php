<?php

namespace App\Services\Observing;

use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\Support\ObservingProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class ObservingSummaryService
{
    public function __construct(
        private readonly SunMoonProvider $sunMoonProvider,
        private readonly WeatherProvider $weatherProvider,
        private readonly AirQualityProvider $airQualityProvider
    ) {
    }

    /**
     * @return array{summary:array<string,mixed>,is_partial:bool}
     */
    public function getSummary(float $lat, float $lon, string $date, string $tz): array
    {
        $providerDebug = [];

        $sun = [
            'sunrise' => null,
            'sunset' => null,
            'civil_twilight_begin' => null,
            'civil_twilight_end' => null,
            'status' => 'unavailable',
        ];

        $moon = [
            'phase_name' => null,
            'illumination_pct' => null,
            'warning' => null,
            'status' => 'unavailable',
        ];

        $humidity = [
            'current_pct' => null,
            'evening_pct' => null,
            'label' => 'Nedostupné',
            'note' => null,
            'status' => 'unavailable',
        ];

        $airQuality = [
            'pm25' => null,
            'pm10' => null,
            'label' => 'Nedostupné',
            'note' => null,
            'source' => 'OpenAQ',
            'status' => 'unavailable',
        ];

        try {
            $sunMoonRaw = $this->sunMoonProvider->get($lat, $lon, $date, $tz);
            $sun = [
                'sunrise' => $sunMoonRaw['sunrise'] ?? null,
                'sunset' => $sunMoonRaw['sunset'] ?? null,
                'civil_twilight_begin' => $sunMoonRaw['civil_twilight_begin'] ?? null,
                'civil_twilight_end' => $sunMoonRaw['civil_twilight_end'] ?? null,
                'status' => $sunMoonRaw['status'] ?? 'unavailable',
            ];

            $illuminationPct = $this->normalizeIlluminationPct($sunMoonRaw['fracillum'] ?? null);
            $moon = [
                'phase_name' => $sunMoonRaw['phase_name'] ?? null,
                'illumination_pct' => $illuminationPct,
                'warning' => ObservingHeuristics::moonWarning($illuminationPct),
                'status' => (($sunMoonRaw['phase_name'] ?? null) || $illuminationPct !== null) ? 'ok' : 'unavailable',
            ];
        } catch (\Throwable $exception) {
            $providerDebug['sun_moon'] = $this->buildProviderFailureDebug('usno', $exception);
            Log::warning('ObserveSummary sun/moon provider failed.', array_merge([
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'tz' => $tz,
            ], $this->buildExceptionContext($exception)));
        }

        try {
            $targetEveningTime = $this->resolveTargetEveningTime($sun['civil_twilight_end']);
            $weatherRaw = $this->weatherProvider->get($lat, $lon, $date, $tz, $targetEveningTime);
            $humidityHeuristic = ObservingHeuristics::humidity($weatherRaw['evening_pct'] ?? $weatherRaw['current_pct'] ?? null);

            $humidity = [
                'current_pct' => $weatherRaw['current_pct'] ?? null,
                'evening_pct' => $weatherRaw['evening_pct'] ?? null,
                'label' => $humidityHeuristic['label'],
                'note' => $humidityHeuristic['note'],
                'status' => $weatherRaw['status'] ?? $humidityHeuristic['status'],
            ];
        } catch (\Throwable $exception) {
            $providerDebug['weather'] = $this->buildProviderFailureDebug('open_meteo', $exception);
            Log::warning('ObserveSummary weather provider failed.', array_merge([
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'tz' => $tz,
            ], $this->buildExceptionContext($exception)));
        }

        try {
            $airRaw = $this->airQualityProvider->get($lat, $lon, $date, $tz);
            $airHeuristic = ObservingHeuristics::airQuality($airRaw['pm25'] ?? null, $airRaw['pm10'] ?? null);

            $airQuality = [
                'pm25' => $airRaw['pm25'] ?? null,
                'pm10' => $airRaw['pm10'] ?? null,
                'label' => $airHeuristic['label'],
                'note' => $airHeuristic['note'],
                'source' => $airRaw['source'] ?? 'OpenAQ',
                'status' => $airRaw['status'] ?? $airHeuristic['status'],
            ];
        } catch (\Throwable $exception) {
            $providerDebug['air_quality'] = $this->buildProviderFailureDebug('openaq', $exception);
            Log::warning('ObserveSummary air-quality provider failed.', array_merge([
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'tz' => $tz,
            ], $this->buildExceptionContext($exception)));
        }

        $overallLabels = [
            $humidity['label'],
            $airQuality['label'],
        ];

        if ($moon['warning']) {
            $overallLabels[] = 'Pozor';
        }

        $summary = [
            'location' => [
                'lat' => round($lat, 6),
                'lon' => round($lon, 6),
                'tz' => $tz,
            ],
            'date' => $date,
            'sun' => $sun,
            'moon' => $moon,
            'atmosphere' => [
                'humidity' => $humidity,
                'air_quality' => $airQuality,
            ],
            'overall' => [
                'label' => ObservingHeuristics::overallLabel($overallLabels),
            ],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $isPartial = in_array('unavailable', [
            $sun['status'],
            $moon['status'],
            $humidity['status'],
            $airQuality['status'],
        ], true);

        $allUnavailable = $sun['status'] === 'unavailable'
            && $moon['status'] === 'unavailable'
            && $humidity['status'] === 'unavailable'
            && $airQuality['status'] === 'unavailable';

        if (app()->environment('local')) {
            if (isset($providerDebug['sun_moon'])) {
                $summary['sun']['debug'] = $providerDebug['sun_moon'];
                $summary['moon']['debug'] = $providerDebug['sun_moon'];
            }
            if (isset($providerDebug['weather'])) {
                $summary['atmosphere']['humidity']['debug'] = $providerDebug['weather'];
            }
            if (isset($providerDebug['air_quality'])) {
                $summary['atmosphere']['air_quality']['debug'] = $providerDebug['air_quality'];
            }
        }

        return [
            'summary' => $summary,
            'is_partial' => $isPartial,
            'all_unavailable' => $allUnavailable,
        ];
    }

    public function diagnostics(float $lat, float $lon, string $date, string $tz): array
    {
        $checks = [
            'usno' => ['status' => 'unavailable', 'http_status' => null, 'reason' => null],
            'open_meteo' => ['status' => 'unavailable', 'http_status' => null, 'reason' => null],
            'openaq' => ['status' => 'unavailable', 'http_status' => null, 'reason' => null],
        ];

        try {
            $this->sunMoonProvider->get($lat, $lon, $date, $tz);
            $checks['usno']['status'] = 'ok';
        } catch (\Throwable $exception) {
            $checks['usno'] = $this->diagnosticFromException($exception);
        }

        try {
            $this->weatherProvider->get($lat, $lon, $date, $tz, null);
            $checks['open_meteo']['status'] = 'ok';
        } catch (\Throwable $exception) {
            $checks['open_meteo'] = $this->diagnosticFromException($exception);
        }

        try {
            $aq = $this->airQualityProvider->get($lat, $lon, $date, $tz);
            if (($aq['status'] ?? 'unavailable') === 'ok') {
                $checks['openaq']['status'] = 'ok';
            } else {
                $checks['openaq'] = [
                    'status' => 'unavailable',
                    'http_status' => null,
                    'reason' => 'OpenAQ API key missing or no PM data nearby.',
                ];
            }
        } catch (\Throwable $exception) {
            $checks['openaq'] = $this->diagnosticFromException($exception);
        }

        return [
            'location' => ['lat' => $lat, 'lon' => $lon, 'tz' => $tz],
            'date' => $date,
            'providers' => $checks,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @deprecated Use getSummary() instead.
     * @return array{summary:array<string,mixed>,is_partial:bool}
     */
    public function buildSummary(float $lat, float $lon, string $date, string $tz): array
    {
        return $this->getSummary($lat, $lon, $date, $tz);
    }

    private function buildProviderFailureDebug(string $provider, \Throwable $exception): array
    {
        if ($exception instanceof ObservingProviderException) {
            return [
                'provider' => $provider,
                'reason' => mb_substr($exception->getMessage(), 0, 200),
                'status' => $exception->status,
            ];
        }

        return [
            'provider' => $provider,
            'reason' => mb_substr($exception->getMessage(), 0, 200),
            'status' => null,
        ];
    }

    private function buildExceptionContext(\Throwable $exception): array
    {
        if ($exception instanceof ObservingProviderException) {
            return $exception->toLogContext();
        }

        return [
            'provider' => null,
            'url' => null,
            'status' => null,
            'body_snippet' => null,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ];
    }

    private function diagnosticFromException(\Throwable $exception): array
    {
        if ($exception instanceof ObservingProviderException) {
            return [
                'status' => 'unavailable',
                'http_status' => $exception->status,
                'reason' => mb_substr($exception->getMessage(), 0, 250),
            ];
        }

        return [
            'status' => 'unavailable',
            'http_status' => null,
            'reason' => mb_substr($exception->getMessage(), 0, 250),
        ];
    }

    private function normalizeIlluminationPct(mixed $fraction): ?int
    {
        if ($fraction === null || !is_numeric($fraction)) {
            return null;
        }

        return (int) round(max(0, min(1, (float) $fraction)) * 100);
    }

    private function resolveTargetEveningTime(?string $civilTwilightEnd): string
    {
        if (!is_string($civilTwilightEnd) || !preg_match('/^(?<h>\d{2}):(?<m>\d{2})$/', $civilTwilightEnd, $match)) {
            return (string) config('observing.defaults.evening_target_time', '21:00');
        }

        $hour = (int) $match['h'];
        $minute = (int) $match['m'];
        $base = CarbonImmutable::create(2000, 1, 1, $hour, $minute, 0, 'UTC');
        return $base->addHour()->format('H:i');
    }
}

<?php

namespace App\Services\Observing;

use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\Support\ObservingProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

class ObservingSummaryService
{
    public function __construct(
        private readonly SunMoonProvider $sunMoonProvider,
        private readonly WeatherProvider $weatherProvider,
        private readonly AirQualityProvider $airQualityProvider,
        private readonly ObservingIndexCalculator $indexCalculator,
        private readonly ObservingWeights $observingWeights
    ) {
    }

    /**
     * @return array{summary:array<string,mixed>,is_partial:bool,all_unavailable:bool}
     */
    public function getSummary(float $lat, float $lon, string $date, string $tz, ?string $mode = null): array
    {
        $resolvedMode = $this->observingWeights->sanitizeMode($mode);
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

        $cloudCover = [
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

        $weatherRaw = null;
        $sunMoonRaw = null;

        $primaryResults = $this->fetchPrimaryProviders($lat, $lon, $date, $tz);

        if (($primaryResults['sun_moon']['ok'] ?? false) === true) {
            $sunMoonRaw = $primaryResults['sun_moon']['payload'] ?? null;
            $this->markProviderSuccess('usno');
        } else {
            $providerDebug['sun_moon'] = $primaryResults['sun_moon']['error'] ?? ['provider' => 'usno', 'reason' => 'Unavailable', 'status' => null];
            $this->markProviderFailure('usno');
        }

        if (($primaryResults['weather']['ok'] ?? false) === true) {
            $weatherRaw = $primaryResults['weather']['payload'] ?? null;
            $this->markProviderSuccess('open_meteo');
        } else {
            $providerDebug['weather'] = $primaryResults['weather']['error'] ?? ['provider' => 'open_meteo', 'reason' => 'Unavailable', 'status' => null];
            $this->markProviderFailure('open_meteo');
        }

        if (is_array($sunMoonRaw)) {
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
        }

        $hourlyTimeline = [];

        if (is_array($weatherRaw)) {
            $targetEveningTime = $this->resolveTargetEveningTime($sun['civil_twilight_end']);
            $selectedEveningHumidity = $this->pickHourlyMetric($weatherRaw['hourly'] ?? [], $targetEveningTime, 'humidity_pct');
            $selectedEveningCloud = $this->pickHourlyMetric($weatherRaw['hourly'] ?? [], $targetEveningTime, 'cloud_cover_pct');
            $selectedEveningWind = $this->pickHourlyMetric($weatherRaw['hourly'] ?? [], $targetEveningTime, 'wind_speed_kmh');

            $humidityHeuristic = ObservingHeuristics::humidity($selectedEveningHumidity ?? $weatherRaw['evening_pct'] ?? $weatherRaw['current_pct'] ?? null);
            $humidity = [
                'current_pct' => $weatherRaw['current_pct'] ?? null,
                'evening_pct' => $selectedEveningHumidity ?? ($weatherRaw['evening_pct'] ?? null),
                'label' => $humidityHeuristic['label'],
                'note' => $humidityHeuristic['note'],
                'status' => $weatherRaw['status'] ?? $humidityHeuristic['status'],
            ];

            $cloudCover = $this->buildCloudCoverPayload(
                $weatherRaw['current_cloud_pct'] ?? null,
                $selectedEveningCloud ?? ($weatherRaw['evening_cloud_pct'] ?? null),
                $weatherRaw['status'] ?? 'unavailable'
            );

            $hourlyTimeline = is_array($weatherRaw['hourly'] ?? null) ? $weatherRaw['hourly'] : [];

            $weatherRaw['selected_evening_wind_kmh'] = $selectedEveningWind ?? ($weatherRaw['evening_wind_kmh'] ?? null);
        }

        $modeWeights = $this->observingWeights->forMode($resolvedMode);
        $needsAirQuality = ((float) ($modeWeights['air_quality'] ?? 0.0)) > 0.0;

        if ($needsAirQuality) {
            if ($this->isCircuitOpen('openaq')) {
                $providerDebug['air_quality'] = [
                    'provider' => 'openaq',
                    'reason' => 'Circuit breaker open.',
                    'status' => null,
                ];
            } else {
                try {
                    $airRaw = $this->fetchAirQualityCached($lat, $lon, $date, $tz);
                    $airHeuristic = ObservingHeuristics::airQuality($airRaw['pm25'] ?? null, $airRaw['pm10'] ?? null);

                    $airQuality = [
                        'pm25' => $airRaw['pm25'] ?? null,
                        'pm10' => $airRaw['pm10'] ?? null,
                        'label' => $airHeuristic['label'],
                        'note' => $airHeuristic['note'],
                        'source' => $airRaw['source'] ?? 'OpenAQ',
                        'status' => $airRaw['status'] ?? $airHeuristic['status'],
                    ];

                    $this->markProviderSuccess('openaq');
                } catch (\Throwable $exception) {
                    $providerDebug['air_quality'] = $this->buildProviderFailureDebug('openaq', $exception);
                    $this->markProviderFailure('openaq');
                    Log::warning('ObserveSummary air-quality provider failed.', array_merge([
                        'lat' => $lat,
                        'lon' => $lon,
                        'date' => $date,
                        'tz' => $tz,
                    ], $this->buildExceptionContext($exception)));
                }
            }
        }

        $indexInput = [
            'humidity_pct' => $humidity['evening_pct'] ?? $humidity['current_pct'],
            'cloud_cover_pct' => $cloudCover['evening_pct'] ?? $cloudCover['current_pct'],
            'pm25' => $airQuality['pm25'],
            'pm10' => $airQuality['pm10'],
            'moon_illumination_pct' => $moon['illumination_pct'],
            'wind_speed_kmh' => $weatherRaw['selected_evening_wind_kmh'] ?? ($weatherRaw['current_wind_kmh'] ?? null),
            'sun' => $sun,
            'date' => $date,
            'tz' => $tz,
        ];

        $indexResult = $this->indexCalculator->calculate($resolvedMode, $indexInput);
        $bestTime = $this->indexCalculator->calculateBestTime($resolvedMode, $hourlyTimeline, $indexInput);

        $isPartial = in_array('unavailable', [
            $sun['status'],
            $moon['status'],
            $humidity['status'],
            $cloudCover['status'],
            $airQuality['status'],
        ], true);

        $allUnavailable = $sun['status'] === 'unavailable'
            && $moon['status'] === 'unavailable'
            && $humidity['status'] === 'unavailable'
            && $cloudCover['status'] === 'unavailable'
            && $airQuality['status'] === 'unavailable';

        $overall = $indexResult['overall'];
        if ($allUnavailable) {
            $overall['label'] = 'Nedostupné';
            $overall['reason'] = 'Data providerov su docasne nedostupne.';
            $overall['alert_level'] = 'severe';
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
                'cloud_cover' => $cloudCover,
                'air_quality' => $airQuality,
                'seeing' => [
                    'score' => $indexResult['seeing']['score'],
                    'formula' => $indexResult['seeing']['formula'],
                    'wind_speed_kmh' => $indexResult['seeing']['wind_speed_kmh'],
                    'humidity_pct' => $indexResult['seeing']['humidity_pct'],
                    'status' => $indexResult['seeing']['wind_speed_kmh'] === null && $indexResult['seeing']['humidity_pct'] === null
                        ? 'unavailable'
                        : 'ok',
                ],
            ],
            'observing_mode' => $resolvedMode,
            'observing_index' => $indexResult['observing_index'],
            'factors' => $indexResult['factors'],
            'weights' => $indexResult['weights'],
            'alerts' => $indexResult['alerts'],
            'overall' => [
                'label' => $overall['label'],
                'reason' => $overall['reason'],
                'alert_level' => $overall['alert_level'],
            ],
            'best_time_local' => $bestTime['best_time_local'],
            'best_time_index' => $bestTime['best_time_index'],
            'best_time_reason' => $bestTime['best_time_reason'],
            'timeline' => [
                'hourly' => $bestTime['series'],
                'sunset' => $sun['sunset'],
                'sunrise' => $sun['sunrise'],
                'civil_twilight_end' => $sun['civil_twilight_end'],
                'civil_twilight_begin' => $sun['civil_twilight_begin'],
            ],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        if (app()->environment('local')) {
            if (isset($providerDebug['sun_moon'])) {
                $summary['sun']['debug'] = $providerDebug['sun_moon'];
                $summary['moon']['debug'] = $providerDebug['sun_moon'];
            }
            if (isset($providerDebug['weather'])) {
                $summary['atmosphere']['humidity']['debug'] = $providerDebug['weather'];
                $summary['atmosphere']['cloud_cover']['debug'] = $providerDebug['weather'];
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
     * @return array{summary:array<string,mixed>,is_partial:bool,all_unavailable:bool}
     */
    public function buildSummary(float $lat, float $lon, string $date, string $tz, ?string $mode = null): array
    {
        return $this->getSummary($lat, $lon, $date, $tz, $mode);
    }

    /**
     * @return array{sun_moon:array<string,mixed>,weather:array<string,mixed>}
     */
    private function fetchPrimaryProviders(float $lat, float $lon, string $date, string $tz): array
    {
        $taskMap = [];
        $results = [
            'sun_moon' => ['ok' => false, 'payload' => null, 'error' => ['provider' => 'usno', 'reason' => 'Unavailable', 'status' => null]],
            'weather' => ['ok' => false, 'payload' => null, 'error' => ['provider' => 'open_meteo', 'reason' => 'Unavailable', 'status' => null]],
        ];

        if (!$this->isCircuitOpen('usno')) {
            $taskMap['sun_moon'] = static fn () => self::fetchSunMoonTask($lat, $lon, $date, $tz);
        } else {
            $results['sun_moon']['error'] = ['provider' => 'usno', 'reason' => 'Circuit breaker open.', 'status' => null];
        }

        if (!$this->isCircuitOpen('open_meteo')) {
            $taskMap['weather'] = static fn () => self::fetchWeatherTask($lat, $lon, $date, $tz);
        } else {
            $results['weather']['error'] = ['provider' => 'open_meteo', 'reason' => 'Circuit breaker open.', 'status' => null];
        }

        if ($taskMap === []) {
            return $results;
        }

        try {
            $driver = $this->resolveConcurrencyDriver();
            $taskResults = Concurrency::driver($driver)->run($taskMap);
        } catch (\Throwable $exception) {
            Log::warning('ObserveSummary concurrent provider execution failed, falling back to sync.', [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            $taskResults = [];
            foreach ($taskMap as $key => $task) {
                $taskResults[$key] = $task();
            }
        }

        foreach ($taskResults as $key => $result) {
            if (!is_array($result)) {
                continue;
            }

            $results[$key] = $result;
        }

        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    private static function fetchSunMoonTask(float $lat, float $lon, string $date, string $tz): array
    {
        try {
            $payload = app(SunMoonProvider::class)->get($lat, $lon, $date, $tz);

            return [
                'ok' => true,
                'payload' => $payload,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'payload' => null,
                'error' => self::serializeTaskException('usno', $exception),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function fetchWeatherTask(float $lat, float $lon, string $date, string $tz): array
    {
        try {
            $payload = app(WeatherProvider::class)->get($lat, $lon, $date, $tz, null);

            return [
                'ok' => true,
                'payload' => $payload,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'payload' => null,
                'error' => self::serializeTaskException('open_meteo', $exception),
            ];
        }
    }

    /**
     * @return array{provider:string,reason:string,status:?int}
     */
    private static function serializeTaskException(string $provider, \Throwable $exception): array
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

    private function resolveConcurrencyDriver(): string
    {
        if (app()->runningUnitTests()) {
            return 'sync';
        }

        $configured = strtolower(trim((string) config('observing.concurrency_driver', 'process')));
        return in_array($configured, ['process', 'fork', 'sync'], true) ? $configured : 'process';
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchAirQualityCached(float $lat, float $lon, string $date, string $tz): array
    {
        $cacheKey = implode(':', [
            'observe_air_quality',
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            $date,
            str_replace(':', '_', $tz),
        ]);

        $ttl = (int) config('observing.cache.ttl_minutes', 15);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes($ttl),
            fn (): array => $this->airQualityProvider->get($lat, $lon, $date, $tz)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $hourly
     */
    private function pickHourlyMetric(array $hourly, string $targetTime, string $field): mixed
    {
        $target = CarbonImmutable::createFromFormat('Y-m-d H:i', '2000-01-01 ' . $targetTime, 'UTC');
        if (!$target) {
            return null;
        }

        $best = null;
        $bestDelta = null;

        foreach ($hourly as $row) {
            if (!is_array($row)) {
                continue;
            }

            $time = $row['local_time'] ?? null;
            if (!is_string($time) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $value = $row[$field] ?? null;
            if ($value === null || !is_numeric($value)) {
                continue;
            }

            $candidate = CarbonImmutable::createFromFormat('Y-m-d H:i', '2000-01-01 ' . $time, 'UTC');
            if (!$candidate) {
                continue;
            }

            $delta = abs($candidate->getTimestamp() - $target->getTimestamp());
            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $best = $value;
            }
        }

        return $best;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCloudCoverPayload(mixed $currentPct, mixed $eveningPct, string $providerStatus): array
    {
        $current = is_numeric($currentPct) ? (int) round((float) $currentPct) : null;
        $evening = is_numeric($eveningPct) ? (int) round((float) $eveningPct) : null;
        $chosen = $evening ?? $current;

        if ($chosen === null) {
            return [
                'current_pct' => $current,
                'evening_pct' => $evening,
                'label' => 'Nedostupné',
                'note' => null,
                'status' => 'unavailable',
            ];
        }

        $label = 'OK';
        if ($chosen > 40 && $chosen <= 70) {
            $label = 'Pozor';
        } elseif ($chosen > 70) {
            $label = 'Zlé';
        }

        return [
            'current_pct' => $current,
            'evening_pct' => $evening,
            'label' => $label,
            'note' => 'Vyssia oblacnost znizuje viditelnost objektov.',
            'status' => $providerStatus === 'ok' ? 'ok' : 'unavailable',
        ];
    }

    /**
     * @return array<string,mixed>
     */
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

    /**
     * @return array<string,mixed>
     */
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

    /**
     * @return array{status:string,http_status:?int,reason:string}
     */
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

    private function isCircuitOpen(string $provider): bool
    {
        $openUntil = (int) Cache::get($this->circuitOpenKey($provider), 0);
        return $openUntil > CarbonImmutable::now()->timestamp;
    }

    private function markProviderSuccess(string $provider): void
    {
        Cache::forget($this->circuitFailureKey($provider));
        Cache::forget($this->circuitOpenKey($provider));
    }

    private function markProviderFailure(string $provider): void
    {
        $failureKey = $this->circuitFailureKey($provider);
        $openKey = $this->circuitOpenKey($provider);
        $failureTtl = (int) config('observing.circuit_breaker.failure_ttl_seconds', 300);
        $threshold = (int) config('observing.circuit_breaker.failure_threshold', 3);
        $cooldown = (int) config('observing.circuit_breaker.cooldown_seconds', 120);

        $count = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $count, now()->addSeconds($failureTtl));

        if ($count >= $threshold) {
            Cache::put($openKey, CarbonImmutable::now()->addSeconds($cooldown)->timestamp, now()->addSeconds($cooldown));
            Cache::forget($failureKey);
        }
    }

    private function circuitFailureKey(string $provider): string
    {
        return 'observing:circuit:' . $provider . ':failures';
    }

    private function circuitOpenKey(string $provider): string
    {
        return 'observing:circuit:' . $provider . ':open_until';
    }
}

<?php

namespace App\Services\Sky;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

class SkyIssPreviewService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array{
     *   available:bool,
     *   next_pass_at?:string,
     *   duration_sec?:int,
     *   max_altitude_deg?:float,
     *   direction_start?:string,
     *   direction_end?:string,
     *   reason?:string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $microserviceBase = rtrim((string) config('observing.sky_summary.microservice_base', ''), '/');
        $endpointPath = '/' . ltrim((string) config('observing.sky_summary.iss_preview_endpoint_path', '/iss-preview'), '/');
        $providerUrl = $microserviceBase !== '' ? $microserviceBase . $endpointPath : '';
        $timeoutSeconds = max(1, (int) config('observing.sky_summary.timeout_seconds', 12));
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');

        try {
            $response = $this->http
                ->timeout($timeoutSeconds)
                ->acceptJson()
                ->get($providerUrl, [
                    'lat' => round($lat, 6),
                    'lon' => round($lon, 6),
                    'tz' => $tz,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Sky ISS preview microservice request failed.', [
                'lat' => $lat,
                'lon' => $lon,
                'tz' => $tz,
                'date' => $localDate,
                'microservice_url' => $providerUrl,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return ['available' => false, 'reason' => 'sky_service_unavailable'];
        }

        if (!$response->successful()) {
            Log::warning('Sky ISS preview microservice returned non-success status.', [
                'lat' => $lat,
                'lon' => $lon,
                'tz' => $tz,
                'date' => $localDate,
                'microservice_url' => $providerUrl,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300),
            ]);

            return ['available' => false, 'reason' => 'sky_service_unavailable'];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return ['available' => false];
        }

        $direct = $this->normalizeDirectPayload($payload, $tz);
        if ($direct !== null) {
            return $direct;
        }

        $rows = is_array($payload['response'] ?? null) ? $payload['response'] : [];
        $first = is_array($rows[0] ?? null) ? $rows[0] : null;
        if ($first === null) {
            return ['available' => false];
        }

        $riseTimestamp = $this->toInt($first['risetime'] ?? $first['rise_time'] ?? null);
        if ($riseTimestamp === null || $riseTimestamp <= 0) {
            return ['available' => false];
        }

        $duration = max(0, $this->toInt($first['duration'] ?? null) ?? 0);
        $maxAltitude = $this->estimateAltitude($duration);
        [$startDirection, $endDirection] = $this->estimateDirections($lat);

        return [
            'available' => true,
            'next_pass_at' => CarbonImmutable::createFromTimestampUTC($riseTimestamp)->setTimezone($tz)->toIso8601String(),
            'duration_sec' => $duration,
            'max_altitude_deg' => $maxAltitude,
            'direction_start' => $startDirection,
            'direction_end' => $endDirection,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function normalizeDirectPayload(array $payload, string $tz): ?array
    {
        $available = $payload['available'] ?? null;
        if (!is_bool($available)) {
            return null;
        }

        if ($available === false) {
            return ['available' => false];
        }

        $nextPassAtRaw = $payload['next_pass_at'] ?? null;
        if (!is_string($nextPassAtRaw) || trim($nextPassAtRaw) === '') {
            return ['available' => false];
        }

        $nextPassAt = $this->toIso8601($nextPassAtRaw, $tz);
        if ($nextPassAt === null) {
            return ['available' => false];
        }

        $duration = max(0, $this->toInt($payload['duration_sec'] ?? null) ?? 0);
        $maxAltitude = $this->toFloat($payload['max_altitude_deg'] ?? null) ?? $this->estimateAltitude($duration);

        return [
            'available' => true,
            'next_pass_at' => $nextPassAt,
            'duration_sec' => $duration,
            'max_altitude_deg' => round(max(0.0, min(90.0, $maxAltitude)), 1),
            'direction_start' => $this->normalizeDirection($payload['direction_start'] ?? null) ?? 'W',
            'direction_end' => $this->normalizeDirection($payload['direction_end'] ?? null) ?? 'E',
        ];
    }

    private function toIso8601(string $value, string $tz): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($trimmed)->setTimezone($tz)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function estimateAltitude(int $durationSec): float
    {
        $estimated = 18.0 + ($durationSec / 18.0);
        return round(max(10.0, min(85.0, $estimated)), 1);
    }

    /**
     * @return array{string,string}
     */
    private function estimateDirections(float $lat): array
    {
        if ($lat < 0) {
            return ['E', 'W'];
        }

        return ['W', 'E'];
    }

    private function normalizeDirection(mixed $value): ?string
    {
        $candidate = strtoupper(trim((string) $value));
        return in_array($candidate, ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'], true)
            ? $candidate
            : null;
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

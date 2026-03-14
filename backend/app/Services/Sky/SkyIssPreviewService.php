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
        $dataSources = [];
        $basePayload = ['available' => false, 'reason' => 'sky_service_unavailable'];

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
            $basePayload = ['available' => false, 'reason' => 'sky_service_unavailable'];
            return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
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
            $basePayload = ['available' => false, 'reason' => 'sky_service_unavailable'];
            return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
        }

        $dataSources[] = 'sky_microservice';

        $payload = $response->json();
        if (!is_array($payload)) {
            $basePayload = ['available' => false];
            return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
        }

        $direct = $this->normalizeDirectPayload($payload, $tz);
        if ($direct !== null) {
            return $this->attachEnrichedMetadata($direct, $dataSources, $tz);
        }

        $rows = is_array($payload['response'] ?? null) ? $payload['response'] : [];
        $first = is_array($rows[0] ?? null) ? $rows[0] : null;
        if ($first === null) {
            $basePayload = ['available' => false];
            return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
        }

        $riseTimestamp = $this->toInt($first['risetime'] ?? $first['rise_time'] ?? null);
        if ($riseTimestamp === null || $riseTimestamp <= 0) {
            $basePayload = ['available' => false];
            return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
        }

        $duration = max(0, $this->toInt($first['duration'] ?? null) ?? 0);
        $maxAltitude = $this->estimateAltitude($duration);
        [$startDirection, $endDirection] = $this->estimateDirections($lat);

        $basePayload = [
            'available' => true,
            'next_pass_at' => CarbonImmutable::createFromTimestampUTC($riseTimestamp)->setTimezone($tz)->toIso8601String(),
            'duration_sec' => $duration,
            'max_altitude_deg' => $maxAltitude,
            'direction_start' => $startDirection,
            'direction_end' => $endDirection,
        ];

        return $this->attachEnrichedMetadata($basePayload, $dataSources, $tz);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $dataSources
     * @return array<string,mixed>
     */
    private function attachEnrichedMetadata(array $payload, array $dataSources, string $tz): array
    {
        $satellite = $this->fetchCelesTrakSatellite($tz);
        if ($satellite !== null) {
            $payload['satellite'] = $satellite;
            $dataSources[] = 'celestrak_gp';
        }

        $tracker = $this->fetchTrackerPosition($tz);
        if ($tracker !== null) {
            $payload['tracker'] = $tracker;
            $dataSources[] = 'iss_tracker';
        }

        if ($dataSources !== []) {
            $payload['data_sources'] = array_values(array_unique($dataSources));
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCelesTrakSatellite(string $tz): ?array
    {
        $providerUrl = trim((string) config('observing.providers.celestrak_gp_url', ''));
        if ($providerUrl === '') {
            return null;
        }
        $timeoutSeconds = max(1, (int) config('observing.sky.iss_aux_timeout_seconds', 6));

        $catnr = (int) config('observing.providers.celestrak_iss_catnr', 25544);
        if ($catnr <= 0) {
            $catnr = 25544;
        }

        try {
            $response = $this->http
                ->timeout($timeoutSeconds)
                ->acceptJson()
                ->get($providerUrl, [
                    'CATNR' => $catnr,
                    'FORMAT' => 'json',
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        $row = is_array($payload[0] ?? null) ? $payload[0] : null;
        if ($row === null) {
            return null;
        }

        $name = trim((string) ($row['OBJECT_NAME'] ?? ''));
        $noradId = $this->toInt($row['NORAD_CAT_ID'] ?? null);
        $epoch = $this->toIso8601((string) ($row['EPOCH'] ?? ''), $tz);
        $meanMotion = $this->toFloat($row['MEAN_MOTION'] ?? null);
        $eccentricity = $this->toFloat($row['ECCENTRICITY'] ?? null);
        $inclination = $this->toFloat($row['INCLINATION'] ?? null);
        $revAtEpoch = $this->toInt($row['REV_AT_EPOCH'] ?? null);

        if ($name === '' || $noradId === null || $epoch === null) {
            return null;
        }

        $result = [
            'source' => 'celestrak_gp',
            'name' => $name,
            'norad_id' => $noradId,
            'epoch' => $epoch,
        ];

        if ($meanMotion !== null) {
            $result['mean_motion'] = round($meanMotion, 8);
        }
        if ($eccentricity !== null) {
            $result['eccentricity'] = round($eccentricity, 8);
        }
        if ($inclination !== null) {
            $result['inclination_deg'] = round($inclination, 4);
        }
        if ($revAtEpoch !== null) {
            $result['rev_at_epoch'] = $revAtEpoch;
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchTrackerPosition(string $tz): ?array
    {
        $providerUrl = trim((string) config('observing.providers.iss_tracker_url', ''));
        if ($providerUrl === '') {
            return null;
        }
        $timeoutSeconds = max(1, (int) config('observing.sky.iss_tracker_timeout_seconds', 10));

        try {
            $response = $this->http
                ->timeout($timeoutSeconds)
                ->acceptJson()
                ->get($providerUrl);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return null;
        }

        $lat = $this->toFloat($payload['latitude'] ?? null);
        $lon = $this->toFloat($payload['longitude'] ?? null);
        if ($lat === null || $lon === null) {
            return null;
        }

        $altitude = $this->toFloat($payload['altitude'] ?? null);
        $velocity = $this->toFloat($payload['velocity'] ?? null);
        $timestamp = $this->toInt($payload['timestamp'] ?? null);
        $sampleAt = $timestamp !== null && $timestamp > 0
            ? CarbonImmutable::createFromTimestampUTC($timestamp)->setTimezone($tz)->toIso8601String()
            : CarbonImmutable::now($tz)->toIso8601String();

        $result = [
            'source' => 'iss_tracker',
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'sample_at' => $sampleAt,
        ];

        if ($altitude !== null) {
            $result['altitude_km'] = round($altitude, 3);
        }

        if ($velocity !== null) {
            $result['velocity_kmh'] = round($velocity, 3);
        }

        $visibility = trim((string) ($payload['visibility'] ?? ''));
        if ($visibility !== '') {
            $result['visibility'] = $visibility;
        }

        return $result;
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

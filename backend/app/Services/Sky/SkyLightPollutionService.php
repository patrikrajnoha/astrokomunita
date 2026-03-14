<?php

namespace App\Services\Sky;

use Illuminate\Http\Client\Factory as HttpFactory;

class SkyLightPollutionService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(float $lat, float $lon): array
    {
        $providers = $this->configuredProviders();

        foreach ($providers as $source => $providerUrl) {
            $normalized = $this->fetchFromProvider($providerUrl, $lat, $lon);
            if ($normalized === null) {
                continue;
            }

            return $this->attachProviderProvenance($normalized, $source, $providerUrl, $lat, $lon);
        }

        $viirsPayload = $this->fetchFromViirs($lat, $lon);
        if ($viirsPayload !== null) {
            return $this->attachViirsProvenance($viirsPayload, $lat, $lon);
        }

        if ($providers === [] && !$this->hasViirsProviderConfigured()) {
            return $this->unavailablePayload('light_pollution_provider_not_configured');
        }

        return $this->unavailablePayload('light_pollution_provider_unavailable');
    }

    /**
     * @return array<string,string>
     */
    private function configuredProviders(): array
    {
        $providers = [
            'light_pollution_provider' => trim((string) config('observing.providers.light_pollution_url', '')),
            'light_pollution_provider_secondary' => trim((string) config('observing.providers.light_pollution_secondary_url', '')),
        ];

        return array_filter($providers, static fn (string $url): bool => $url !== '');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchFromProvider(string $providerUrl, float $lat, float $lon): ?array
    {
        try {
            $response = $this->http
                ->timeout(6)
                ->acceptJson()
                ->get($providerUrl, [
                    'lat' => round($lat, 6),
                    'lon' => round($lon, 6),
                ]);
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

        return $this->normalizeProviderPayload($payload);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchFromViirs(float $lat, float $lon): ?array
    {
        $viirsUrl = trim((string) config('observing.providers.light_pollution_viirs_url', ''));
        if ($viirsUrl === '') {
            return null;
        }

        try {
            $response = $this->http
                ->timeout(6)
                ->acceptJson()
                ->get($viirsUrl, [
                    'geometry' => number_format($lon, 6, '.', '').','.number_format($lat, 6, '.', ''),
                    'geometryType' => 'esriGeometryPoint',
                    'returnFirstValueOnly' => 'true',
                    'interpolation' => 'RSP_NearestNeighbor',
                    'f' => 'pjson',
                ]);
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

        return $this->normalizeViirsPayload($payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function normalizeViirsPayload(array $payload): ?array
    {
        $samples = $payload['samples'] ?? null;
        if (!is_array($samples) || $samples === []) {
            return null;
        }

        $firstSample = $samples[0] ?? null;
        if (!is_array($firstSample)) {
            return null;
        }

        $radiance = $this->toFloat(
            $firstSample['value']
                ?? (is_array($firstSample['attributes'] ?? null)
                    ? ($firstSample['attributes']['value'] ?? $firstSample['attributes']['Value'] ?? null)
                    : null)
        );

        if ($radiance === null || $radiance < 0 || $radiance > 10000) {
            return null;
        }

        $resolution = $this->toFloat($firstSample['resolution'] ?? null);
        $rasterId = $this->toInt($firstSample['rasterId'] ?? $firstSample['raster_id'] ?? null);
        $bortle = $this->radianceToBortle($radiance);

        return [
            'bortle_class' => $bortle,
            'brightness_value' => round($this->bortleToBrightness($bortle), 3),
            'confidence' => $this->resolveViirsConfidence(
                $radiance,
                $resolution
            ),
            'measurement' => [
                'kind' => 'viirs_radiance',
                'viirs_radiance_nw_cm2_sr' => round($radiance, 3),
                'viirs_resolution_deg' => $resolution !== null ? round($resolution, 6) : null,
                'viirs_raster_id' => $rasterId,
                'bortle_mapping_version' => 'viirs_radiance_to_bortle_v1',
            ],
        ];
    }

    private function hasViirsProviderConfigured(): bool
    {
        return trim((string) config('observing.providers.light_pollution_viirs_url', '')) !== '';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function normalizeProviderPayload(array $payload): ?array
    {
        $container = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $rawBrightness = $container['brightness_value']
            ?? $container['brightness']
            ?? $container['value']
            ?? null;
        $brightness = $this->normalizeBrightness($rawBrightness);

        $rawBortle = $container['bortle_class'] ?? $container['bortle'] ?? null;
        $bortle = $this->toInt($rawBortle);
        if ($bortle === null && $brightness !== null) {
            $bortle = $this->brightnessToBortle($brightness);
        }

        if ($brightness === null && $bortle === null) {
            return null;
        }

        $safeBrightness = $brightness !== null ? round($brightness, 3) : null;
        $safeBortle = $bortle !== null ? max(1, min(9, $bortle)) : null;

        if ($safeBrightness === null && $safeBortle !== null) {
            $safeBrightness = round($this->bortleToBrightness($safeBortle), 3);
        }

        if ($safeBortle === null && $safeBrightness !== null) {
            $safeBortle = $this->brightnessToBortle($safeBrightness);
        }

        return [
            'bortle_class' => $safeBortle,
            'brightness_value' => $safeBrightness,
            'confidence' => $this->normalizeConfidence($container['confidence'] ?? null) ?? 'high',
            'measurement' => [
                'kind' => 'provider_normalized',
                'raw_bortle_class' => $this->toInt($rawBortle),
                'raw_brightness_value' => $this->toFloat($rawBrightness),
                'normalization' => 'provider_value_to_bortle_v1',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function unavailablePayload(string $reason): array
    {
        return [
            'bortle_class' => null,
            'brightness_value' => null,
            'confidence' => 'low',
            'source' => 'light_pollution_provider',
            'reason' => $reason,
            'measurement' => null,
            'provenance' => [
                'method' => 'unavailable',
                'provider_chain' => ['light_pollution_provider', 'light_pollution_provider_secondary', 'light_pollution_viirs'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function attachProviderProvenance(
        array $payload,
        string $source,
        string $providerUrl,
        float $lat,
        float $lon
    ): array {
        return [
            ...$payload,
            'source' => $source,
            'reason' => null,
            'provenance' => [
                'method' => 'provider_http_get',
                'provider_key' => $source,
                'provider_url' => $this->providerUrlWithoutQuery($providerUrl),
                'query_lat' => round($lat, 6),
                'query_lon' => round($lon, 6),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function attachViirsProvenance(array $payload, float $lat, float $lon): array
    {
        $viirsUrl = trim((string) config('observing.providers.light_pollution_viirs_url', ''));

        return [
            ...$payload,
            'source' => 'light_pollution_viirs',
            'reason' => null,
            'provenance' => [
                'method' => 'viirs_get_samples_nearest_neighbor',
                'provider_key' => 'light_pollution_viirs',
                'provider_url' => $this->providerUrlWithoutQuery($viirsUrl),
                'query_lat' => round($lat, 6),
                'query_lon' => round($lon, 6),
            ],
        ];
    }

    private function providerUrlWithoutQuery(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts)) {
            return $trimmed;
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']).'://' : '';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';

        return $scheme.$host.$port.$path;
    }

    private function normalizeBrightness(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric > 1.0 && $numeric <= 100.0) {
            $numeric = $numeric / 100.0;
        }

        return max(0.0, min(1.0, $numeric));
    }

    private function brightnessToBortle(float $brightness): int
    {
        return max(1, min(9, (int) round(4 + ($brightness * 16))));
    }

    private function bortleToBrightness(int $bortle): float
    {
        $safe = max(1, min(9, $bortle));
        return max(0.0, min(1.0, ($safe - 1) / 8.0));
    }

    private function normalizeConfidence(mixed $value): ?string
    {
        $candidate = strtolower(trim((string) $value));
        return in_array($candidate, ['low', 'med', 'high'], true) ? $candidate : null;
    }

    private function resolveViirsConfidence(float $radiance, ?float $resolution): string
    {
        if ($radiance <= 120.0 && $resolution !== null && $resolution <= 0.01) {
            return 'med';
        }

        return 'low';
    }

    private function radianceToBortle(float $radiance): int
    {
        $safeRadiance = max(0.0, $radiance);

        return match (true) {
            $safeRadiance < 0.05 => 1,
            $safeRadiance < 0.15 => 2,
            $safeRadiance < 0.35 => 3,
            $safeRadiance < 0.80 => 4,
            $safeRadiance < 2.00 => 5,
            $safeRadiance < 6.00 => 6,
            $safeRadiance < 20.00 => 7,
            $safeRadiance < 45.00 => 8,
            default => 9,
        };
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

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
     * @return array{
     *   bortle_class:int,
     *   brightness_value:float,
     *   confidence:string
     * }
     */
    public function fetch(float $lat, float $lon): array
    {
        $providerUrl = trim((string) config('observing.providers.light_pollution_url', ''));
        if ($providerUrl !== '') {
            try {
                $response = $this->http
                    ->timeout(6)
                    ->acceptJson()
                    ->get($providerUrl, [
                        'lat' => round($lat, 6),
                        'lon' => round($lon, 6),
                    ]);

                if ($response->successful()) {
                    $payload = $response->json();
                    if (is_array($payload)) {
                        $normalized = $this->normalizeProviderPayload($payload);
                        if ($normalized !== null) {
                            return $normalized;
                        }
                    }
                }
            } catch (\Throwable) {
                // Keep fallback payload when provider fails.
            }
        }

        return $this->fallbackPayload($lat, $lon);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{bortle_class:int,brightness_value:float,confidence:string}|null
     */
    private function normalizeProviderPayload(array $payload): ?array
    {
        $container = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $brightness = $this->normalizeBrightness(
            $container['brightness_value']
                ?? $container['brightness']
                ?? $container['value']
                ?? null
        );

        $bortle = $this->toInt($container['bortle_class'] ?? $container['bortle'] ?? null);
        if ($bortle === null && $brightness !== null) {
            $bortle = $this->brightnessToBortle($brightness);
        }

        if ($brightness === null && $bortle === null) {
            return null;
        }

        $safeBrightness = $brightness ?? $this->bortleToBrightness($bortle ?? 6);
        $safeBortle = max(1, min(9, $bortle ?? $this->brightnessToBortle($safeBrightness)));

        return [
            'bortle_class' => $safeBortle,
            'brightness_value' => round($safeBrightness, 3),
            'confidence' => $this->normalizeConfidence($container['confidence'] ?? null) ?? 'high',
        ];
    }

    /**
     * @return array{bortle_class:int,brightness_value:float,confidence:string}
     */
    private function fallbackPayload(float $lat, float $lon): array
    {
        // Deterministic fallback keeps stable values for same coordinates.
        $variation = (sin(deg2rad($lat * 3.0)) + cos(deg2rad($lon * 2.0))) * 0.03;
        $brightness = max(0.02, min(0.35, 0.123 + $variation));
        $bortle = $this->brightnessToBortle($brightness);

        return [
            'bortle_class' => $bortle,
            'brightness_value' => round($brightness, 3),
            'confidence' => 'low',
        ];
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

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}

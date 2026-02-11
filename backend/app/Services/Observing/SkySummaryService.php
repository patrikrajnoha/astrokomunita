<?php

namespace App\Services\Observing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class SkySummaryService
{
    public function __construct(
        private readonly SkyMicroserviceClient $skyMicroserviceClient,
        private readonly MeteorShowerService $meteorShowerService
    ) {
    }

    /**
     * @return array{meta:array<string,mixed>,moon:array<string,mixed>|null,planets:array<int,array<string,mixed>>,meteors:array<int,array<string,mixed>>,comets:array<int,array<string,mixed>>}
     */
    public function getSummary(float $lat, float $lon, string $date, string $tz): array
    {
        $sanitizedTz = $this->sanitizeTimezone($tz);
        $meta = [
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'tz' => $sanitizedTz['tz'],
            'date' => $date,
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        if ($sanitizedTz['warning']) {
            $meta['warning'] = $sanitizedTz['warning'];
        }

        $moon = null;
        $planets = [];

        try {
            $microservice = $this->skyMicroserviceClient->fetch($lat, $lon, $date, $sanitizedTz['tz']);

            $moon = $this->normalizeMoon($microservice['moon'] ?? null);
            $planets = $this->normalizePlanets($microservice['planets'] ?? []);
        } catch (\Throwable $exception) {
            $meta['error'] = mb_substr($exception->getMessage(), 0, 240);

            Log::warning('Sky summary microservice failed.', [
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'tz' => $sanitizedTz['tz'],
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }

        return [
            'meta' => $meta,
            'moon' => $moon,
            'planets' => array_slice($planets, 0, 3),
            'meteors' => $this->meteorShowerService->activeForDate($date),
            'comets' => [],
        ];
    }

    /**
     * @param mixed $payload
     * @return array<string,mixed>|null
     */
    private function normalizeMoon(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        return [
            'phase_deg' => $this->roundFloat($payload['phase_deg'] ?? null, 1),
            'phase_name' => $this->cleanString($payload['phase_name'] ?? null),
            'illumination' => $this->roundFloat($payload['illumination'] ?? null, 1),
            'rise_local' => $this->toTimeOrNull($payload['rise_local'] ?? null),
            'set_local' => $this->toTimeOrNull($payload['set_local'] ?? null),
        ];
    }

    /**
     * @param mixed $payload
     * @return array<int,array<string,mixed>>
     */
    private function normalizePlanets(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $items = [];

        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }

            $altMax = $this->roundFloat($item['alt_max_deg'] ?? null, 1);
            $bestFrom = $this->toTimeOrNull($item['best_from'] ?? null);
            $bestTo = $this->toTimeOrNull($item['best_to'] ?? null);

            if (!is_numeric($altMax) || $altMax < 10 || !$bestFrom || !$bestTo) {
                continue;
            }

            $direction = strtoupper((string) ($item['direction'] ?? ''));
            if (!in_array($direction, ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'], true)) {
                $direction = 'N';
            }

            $items[] = [
                'key' => $this->cleanString($item['key'] ?? null) ?? 'planet',
                'name' => $this->cleanString($item['name'] ?? null) ?? 'Planeta',
                'best_from' => $bestFrom,
                'best_to' => $bestTo,
                'direction' => $direction,
                'alt_max_deg' => $altMax,
                'az_at_best_deg' => $this->roundFloat($item['az_at_best_deg'] ?? null, 1),
                'is_low' => $altMax < 15,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['alt_max_deg'] <=> $a['alt_max_deg']) ?: strcmp($a['name'], $b['name']);
        });

        return array_values($items);
    }

    private function roundFloat(mixed $value, int $precision): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, $precision);
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function toTimeOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return preg_match('/^\d{2}:\d{2}$/', $trimmed) ? $trimmed : null;
    }

    /**
     * @return array{tz:string,warning:?string}
     */
    private function sanitizeTimezone(string $raw): array
    {
        $trimmed = trim($raw, " \t\n\r\0\x0B\"'");
        $fallback = (string) config('app.timezone', 'UTC');
        if (!in_array($fallback, timezone_identifiers_list(), true)) {
            $fallback = 'UTC';
        }

        if ($trimmed === '') {
            return [
                'tz' => $fallback,
                'warning' => null,
            ];
        }

        if (in_array($trimmed, timezone_identifiers_list(), true)) {
            return [
                'tz' => $trimmed,
                'warning' => null,
            ];
        }

        return [
            'tz' => $fallback,
            'warning' => "Invalid timezone '{$trimmed}' received; fallback to '{$fallback}'.",
        ];
    }
}

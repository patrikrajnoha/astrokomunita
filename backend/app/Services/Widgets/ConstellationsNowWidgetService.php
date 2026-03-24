<?php

namespace App\Services\Widgets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ConstellationsNowWidgetService
{
    private const CACHE_KEY_PREFIX = 'widget:constellations-now:v1';
    private const MIN_ITEMS = 3;
    private const MAX_ITEMS = 5;
    private const DEFAULT_ITEMS = 4;

    /**
     * @param  array{lat:float,lon:float,tz:string}|null  $context
     * @param  array<string,mixed>|null  $weatherPayload
     * @return array<string,mixed>
     */
    public function payload(?array $context = null, ?array $weatherPayload = null): array
    {
        $resolvedContext = $this->resolveContext($context);
        $nowLocal = CarbonImmutable::now($resolvedContext['tz']);
        $month = (int) $nowLocal->month;
        $itemsLimit = $this->resolveItemsLimit();
        $cacheKey = $this->buildCacheKey($resolvedContext, $nowLocal, $itemsLimit);
        $ttlMinutes = max((int) config('widgets.constellations_now.cache_ttl_minutes', 180), 1);

        $payload = Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use (
            $resolvedContext,
            $nowLocal,
            $month,
            $itemsLimit
        ): array {
            $dataset = $this->loadDataset();
            $items = $this->selectItems($dataset, $month, $itemsLimit);

            return [
                'available' => $items !== [],
                'items' => $items,
                'meta' => [
                    'reference_date' => $nowLocal->format('Y-m-d'),
                    'reference_month' => $month,
                    'reference_month_label' => $this->monthLabel($month),
                    'timezone' => $resolvedContext['tz'],
                    'location_label' => $resolvedContext['location_label'],
                    'selection_engine' => 'mvp_month_weight_v1',
                    'default_location_used' => $resolvedContext['default_location_used'],
                ],
                'source' => [
                    'provider' => 'astrokomunita_internal_dataset',
                    'label' => 'Internal constellation dataset',
                    'api_key_required' => false,
                ],
                'generated_at' => now()->toIso8601String(),
            ];
        });

        $eveningCloudPercent = $this->resolveEveningCloudPercent($weatherPayload);
        if ($eveningCloudPercent !== null) {
            $payload['meta']['evening_cloud_percent'] = $eveningCloudPercent;
        }

        return $payload;
    }

    /**
     * @param  list<array<string,mixed>>  $dataset
     * @return array<int,array<string,mixed>>
     */
    private function selectItems(array $dataset, int $month, int $itemsLimit): array
    {
        if ($dataset === []) {
            return [];
        }

        $ranked = [];

        foreach ($dataset as $entry) {
            $monthWeight = $this->monthWeightForEntry($entry, $month);
            $fallbackWeight = $this->fallbackWeightForEntry($entry);
            $ranked[] = [
                'entry' => $entry,
                'month_weight' => $monthWeight,
                'fallback_weight' => $fallbackWeight,
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            if ($left['month_weight'] !== $right['month_weight']) {
                return $right['month_weight'] <=> $left['month_weight'];
            }

            if ($left['fallback_weight'] !== $right['fallback_weight']) {
                return $right['fallback_weight'] <=> $left['fallback_weight'];
            }

            return strcmp(
                (string) ($left['entry']['display_name'] ?? $left['entry']['name'] ?? ''),
                (string) ($right['entry']['display_name'] ?? $right['entry']['name'] ?? '')
            );
        });

        $selected = [];
        $selectedNameSet = [];

        foreach ($ranked as $candidate) {
            if ((int) $candidate['month_weight'] <= 0) {
                continue;
            }

            $name = (string) ($candidate['entry']['name'] ?? '');
            if ($name === '' || isset($selectedNameSet[$name])) {
                continue;
            }

            $selected[] = $candidate;
            $selectedNameSet[$name] = true;

            if (count($selected) >= $itemsLimit) {
                break;
            }
        }

        if (count($selected) < self::MIN_ITEMS) {
            foreach ($ranked as $candidate) {
                $name = (string) ($candidate['entry']['name'] ?? '');
                if ($name === '' || isset($selectedNameSet[$name])) {
                    continue;
                }

                $selected[] = $candidate;
                $selectedNameSet[$name] = true;

                if (count($selected) >= self::MIN_ITEMS) {
                    break;
                }
            }
        }

        $selected = array_slice($selected, 0, $itemsLimit);

        return array_map(static fn (array $selectedEntry): array => [
            'name' => (string) $selectedEntry['entry']['name'],
            'display_name' => (string) $selectedEntry['entry']['display_name'],
            'localized_name' => (string) $selectedEntry['entry']['display_name'],
            'direction' => (string) $selectedEntry['entry']['direction'],
            'best_time' => (string) $selectedEntry['entry']['best_time'],
            'visibility' => [
                'level' => (string) $selectedEntry['entry']['visibility_level'],
                'label' => (string) $selectedEntry['entry']['visibility_text'],
            ],
            'visibility_level' => (string) $selectedEntry['entry']['visibility_level'],
            'visibility_text' => (string) $selectedEntry['entry']['visibility_text'],
            'season' => (string) $selectedEntry['entry']['season'],
            'month_weight' => (int) $selectedEntry['month_weight'],
            'short_hint' => (string) $selectedEntry['entry']['short_hint'],
        ], $selected);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadDataset(): array
    {
        $path = resource_path('data/constellations_now.json');

        if (! File::exists($path)) {
            Log::warning('Constellations dataset file missing.', ['path' => $path]);
            return [];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            Log::warning('Constellations dataset JSON is invalid.', ['path' => $path]);
            return [];
        }

        $normalized = [];
        foreach ($decoded as $entry) {
            $normalizedEntry = $this->normalizeDatasetEntry($entry);
            if ($normalizedEntry !== null) {
                $normalized[] = $normalizedEntry;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function normalizeDatasetEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $name = trim((string) ($entry['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $displayName = trim((string) ($entry['display_name'] ?? $name));
        $season = trim((string) ($entry['season'] ?? 'all_year'));
        $direction = trim((string) ($entry['direction'] ?? 'sever'));
        $bestTime = trim((string) ($entry['best_time'] ?? 'cely vecer'));
        $visibilityLevel = $this->normalizeVisibilityLevel($entry['visibility_level'] ?? null);
        $visibilityText = trim((string) ($entry['visibility_text'] ?? $this->visibilityLabelForLevel($visibilityLevel)));
        $shortHint = trim((string) ($entry['short_hint'] ?? ''));

        return [
            'name' => $name,
            'display_name' => $displayName !== '' ? $displayName : $name,
            'season' => $season !== '' ? $season : 'all_year',
            'primary_months' => $this->normalizeMonthList($entry['primary_months'] ?? []),
            'secondary_months' => $this->normalizeMonthList($entry['secondary_months'] ?? []),
            'is_circumpolar' => (bool) ($entry['is_circumpolar'] ?? false),
            'direction' => $direction !== '' ? $direction : 'sever',
            'best_time' => $bestTime !== '' ? $bestTime : 'cely vecer',
            'visibility_level' => $visibilityLevel,
            'visibility_text' => $visibilityText !== '' ? $visibilityText : $this->visibilityLabelForLevel($visibilityLevel),
            'short_hint' => $shortHint,
        ];
    }

    /**
     * @return list<int>
     */
    private function normalizeMonthList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (! is_numeric($item)) {
                continue;
            }

            $month = (int) $item;
            if ($month < 1 || $month > 12) {
                continue;
            }

            $result[$month] = $month;
        }

        return array_values($result);
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function monthWeightForEntry(array $entry, int $month): int
    {
        $primaryMonths = is_array($entry['primary_months'] ?? null) ? $entry['primary_months'] : [];
        $secondaryMonths = is_array($entry['secondary_months'] ?? null) ? $entry['secondary_months'] : [];
        $isCircumpolar = (bool) ($entry['is_circumpolar'] ?? false);

        if (in_array($month, $primaryMonths, true)) {
            return 100 + ($isCircumpolar ? 5 : 0);
        }

        if (in_array($month, $secondaryMonths, true)) {
            return 70 + ($isCircumpolar ? 5 : 0);
        }

        if ($isCircumpolar) {
            return 45;
        }

        return 0;
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function fallbackWeightForEntry(array $entry): int
    {
        $primaryMonths = is_array($entry['primary_months'] ?? null) ? $entry['primary_months'] : [];
        $secondaryMonths = is_array($entry['secondary_months'] ?? null) ? $entry['secondary_months'] : [];
        $weight = count($primaryMonths) * 4 + count($secondaryMonths) * 2;

        if ((bool) ($entry['is_circumpolar'] ?? false)) {
            $weight += 18;
        }

        return $weight;
    }

    private function normalizeVisibilityLevel(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'high' || $normalized === 'medium') {
            return $normalized;
        }

        return 'medium';
    }

    private function visibilityLabelForLevel(string $level): string
    {
        return $level === 'high' ? 'Lahko viditelne' : 'Stredne viditelne';
    }

    /**
     * @param  array{lat:float,lon:float,tz:string}|null  $context
     * @return array{lat:float,lon:float,tz:string,location_label:string,default_location_used:bool}
     */
    private function resolveContext(?array $context): array
    {
        $fallbackLat = (float) config('observing.sky_context.fallback_lat', 48.1486);
        $fallbackLon = (float) config('observing.sky_context.fallback_lon', 17.1077);
        $fallbackTz = trim((string) config('observing.sky_context.fallback_tz', 'Europe/Bratislava'));
        $fallbackTz = in_array($fallbackTz, timezone_identifiers_list(), true)
            ? $fallbackTz
            : 'Europe/Bratislava';

        if (
            is_array($context)
            && is_numeric($context['lat'] ?? null)
            && is_numeric($context['lon'] ?? null)
            && is_string($context['tz'] ?? null)
            && in_array((string) $context['tz'], timezone_identifiers_list(), true)
        ) {
            return [
                'lat' => round((float) $context['lat'], 6),
                'lon' => round((float) $context['lon'], 6),
                'tz' => (string) $context['tz'],
                'location_label' => 'User context',
                'default_location_used' => false,
            ];
        }

        return [
            'lat' => round($fallbackLat, 6),
            'lon' => round($fallbackLon, 6),
            'tz' => $fallbackTz,
            'location_label' => 'Slovensko (default)',
            'default_location_used' => true,
        ];
    }

    /**
     * @param  array{lat:float,lon:float,tz:string,location_label:string,default_location_used:bool}  $context
     */
    private function buildCacheKey(array $context, CarbonImmutable $moment, int $itemsLimit): string
    {
        $tz = str_replace(':', '_', $context['tz']);

        return implode(':', [
            self::CACHE_KEY_PREFIX,
            $moment->format('Y-m'),
            number_format($context['lat'], 3, '.', ''),
            number_format($context['lon'], 3, '.', ''),
            $tz,
            (string) $itemsLimit,
        ]);
    }

    private function resolveItemsLimit(): int
    {
        $configured = (int) config('widgets.constellations_now.items_limit', self::DEFAULT_ITEMS);

        return max(self::MIN_ITEMS, min(self::MAX_ITEMS, $configured));
    }

    private function monthLabel(int $month): string
    {
        return match ($month) {
            1 => 'januar',
            2 => 'februar',
            3 => 'marec',
            4 => 'april',
            5 => 'maj',
            6 => 'jun',
            7 => 'jul',
            8 => 'august',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'december',
            default => 'neznamy',
        };
    }

    /**
     * @param  array<string,mixed>|null  $weatherPayload
     */
    private function resolveEveningCloudPercent(?array $weatherPayload): ?int
    {
        if (! is_array($weatherPayload)) {
            return null;
        }

        $raw = $weatherPayload['evening_cloud_percent'] ?? $weatherPayload['cloud_percent'] ?? null;
        if (! is_numeric($raw)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $raw)));
    }
}

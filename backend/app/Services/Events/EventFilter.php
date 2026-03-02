<?php

namespace App\Services\Events;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class EventFilter
{
    public const SCOPE_FUTURE = 'future';
    public const SCOPE_PAST = 'past';
    public const SCOPE_ALL = 'all';

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function normalize(array $validated): array
    {
        $normalized = $this->applyPeriodWrappers($validated);
        $normalized['has_explicit_date_range'] = !empty($validated['from']) && !empty($validated['to']);
        $normalized['scope'] = $this->resolveScope($validated['scope'] ?? null);
        $normalized['types'] = $this->resolveRequestedTypes($validated);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $nowUtc = $this->nowUtc();

        $this->applyTypeFilter($query, $filters);
        $this->applyRegionFilter($query, $filters);
        $this->applyTextFilter($query, $filters);
        $this->applyDateRangeFilter($query, $filters);
        $this->applyScopeFilter($query, (string) $filters['scope'], $nowUtc);
        $this->applyOrdering($query, (string) $filters['scope'], $nowUtc);

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [
            self::SCOPE_FUTURE,
            self::SCOPE_PAST,
            self::SCOPE_ALL,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function shouldBypassPagination(array $filters): bool
    {
        return !empty($filters['has_explicit_date_range']);
    }

    private function nowUtc(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyTypeFilter(Builder $query, array $filters): void
    {
        $types = $filters['types'] ?? [];

        if (!is_array($types) || $types === []) {
            return;
        }

        $query->whereIn('type', $types);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyRegionFilter(Builder $query, array $filters): void
    {
        $region = $filters['region'] ?? null;

        if (
            !Event::supportsRegionScope()
            || !is_string($region)
            || !in_array($region, RegionScope::values(), true)
        ) {
            return;
        }

        $query->where('region_scope', $region);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyTextFilter(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $sub) use ($search): void {
            $sub->where('title', 'like', "%{$search}%")
                ->orWhere('short', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyDateRangeFilter(Builder $query, array $filters): void
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $eventDate = $this->eventDateExpression($query);

        if (!empty($from) && !empty($to)) {
            $query->whereRaw("{$eventDate} BETWEEN ? AND ?", [$from, $to]);

            return;
        }

        if (!empty($from)) {
            $query->whereRaw("{$eventDate} >= ?", [$from]);
        }

        if (!empty($to)) {
            $query->whereRaw("{$eventDate} <= ?", [$to]);
        }
    }

    private function applyScopeFilter(Builder $query, string $scope, CarbonImmutable $nowUtc): void
    {
        $threshold = $nowUtc->toDateTimeString();
        $eventDate = $this->eventDateExpression($query);

        if ($scope === self::SCOPE_FUTURE) {
            $query->whereRaw("{$eventDate} >= ?", [$threshold]);

            return;
        }

        if ($scope === self::SCOPE_PAST) {
            $query->whereRaw("{$eventDate} < ?", [$threshold]);
        }
    }

    private function applyOrdering(Builder $query, string $scope, CarbonImmutable $nowUtc): void
    {
        $eventDate = $this->eventDateExpression($query);

        if ($scope === self::SCOPE_PAST) {
            $query->orderByRaw("{$eventDate} DESC")
                ->orderByDesc('id');

            return;
        }

        if ($scope === self::SCOPE_FUTURE) {
            $query->orderByRaw("{$eventDate} ASC")
                ->orderBy('id');

            return;
        }

        $threshold = $nowUtc->toDateTimeString();

        $query->orderByRaw("CASE WHEN {$eventDate} >= ? THEN 0 ELSE 1 END ASC", [$threshold])
            ->orderByRaw("CASE WHEN {$eventDate} >= ? THEN {$eventDate} END ASC", [$threshold])
            ->orderByRaw("CASE WHEN {$eventDate} < ? THEN {$eventDate} END DESC", [$threshold])
            ->orderBy('id');
    }

    private function eventDateExpression(Builder $query): string
    {
        if (Event::supportsEventDateColumn()) {
            return $query->qualifyColumn('event_date');
        }

        return sprintf(
            'COALESCE(%s, %s)',
            $query->qualifyColumn('start_at'),
            $query->qualifyColumn('max_at')
        );
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function applyPeriodWrappers(array $validated): array
    {
        if (!empty($validated['from']) || !empty($validated['to'])) {
            return $validated;
        }

        $timezone = (string) config('events.timezone', config('app.timezone', 'UTC'));
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;
        $week = isset($validated['week']) ? (int) $validated['week'] : null;

        if ($year === null && ($month !== null || $week !== null)) {
            $minYear = (int) config('events.astropixels.min_year', 2021);
            $maxYear = (int) config('events.astropixels.max_year', 2030);
            $year = max($minYear, min($maxYear, (int) now($timezone)->year));
        }

        if ($year === null) {
            return $validated;
        }

        if ($week !== null) {
            $maxIsoWeeks = CarbonImmutable::create($year, 12, 28, 0, 0, 0, $timezone)->isoWeek();
            $resolvedWeek = min($week, $maxIsoWeeks);
            $startLocal = CarbonImmutable::create($year, 1, 4, 0, 0, 0, $timezone)
                ->setISODate($year, $resolvedWeek, 1)
                ->startOfDay();
            $endLocal = $startLocal->addDays(6)->endOfDay();
        } elseif ($month !== null) {
            $startLocal = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->startOfDay();
            $endLocal = $startLocal->endOfMonth()->endOfDay();
        } else {
            $startLocal = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
            $endLocal = $startLocal->endOfYear()->endOfDay();
        }

        $validated['from'] = $startLocal->utc()->toDateTimeString();
        $validated['to'] = $endLocal->utc()->toDateTimeString();

        return $validated;
    }

    private function resolveScope(mixed $scope): string
    {
        return is_string($scope) && in_array($scope, self::scopes(), true)
            ? $scope
            : self::SCOPE_FUTURE;
    }

    /**
     * @param array<string, mixed> $validated
     * @return list<string>
     */
    private function resolveRequestedTypes(array $validated): array
    {
        $supported = EventType::values();
        $rawTypes = $validated['types'] ?? [];

        if (is_string($rawTypes)) {
            $rawTypes = array_map('trim', explode(',', $rawTypes));
        }

        $types = collect(is_array($rawTypes) ? $rawTypes : [])
            ->filter(static fn ($type) => is_string($type) && in_array($type, $supported, true));

        if (
            !empty($validated['type'])
            && is_string($validated['type'])
            && in_array($validated['type'], $supported, true)
        ) {
            $types->push($validated['type']);
        }

        return $types->unique()->values()->all();
    }
}

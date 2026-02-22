<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Events\PublishedEventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {
    }

    /**
     * GET /api/events
     * Filtre: type, from, to, q, per_page
     */
    public function index(EventIndexRequest $request)
    {
        $v = $request->validated();
        $v = $this->applyPeriodWrappers($v);

        $feed = $v['feed'] ?? 'all';
        $user = $request->user();

        if ($feed === 'mine' && !$user) {
            return response()->json([
                'message' => 'Prihlas sa pre personalizovany feed.',
            ], 401);
        }

        $query = $this->basePublishedQuery();

        if ($feed === 'mine') {
            $query->forUser($user);
        }

        $requestedTypes = $this->resolveRequestedTypes($v);
        if ($requestedTypes !== []) {
            $query->whereIn('type', $requestedTypes);
        }

        if (
            Event::supportsRegionScope()
            && !empty($v['region'])
            && in_array($v['region'], RegionScope::values(), true)
        ) {
            $query->where('region_scope', $v['region']);
        }

        // Filter: type
        if (!empty($v['q'])) {
            $q = $v['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('short', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        /**
         * Filter: date range
         * PrimĂˇrne podÄľa start_at, fallback na max_at.
         */
        $hasFrom = !empty($v['from']);
        $hasTo = !empty($v['to']);

        if ($hasFrom && $hasTo) {
            $query->whereBetween('start_at', [$v['from'], $v['to']]);
        } else {
            if ($hasFrom) {
                $from = $v['from'];
                $query->where(function ($sub) use ($from) {
                    $sub->where('start_at', '>=', $from)
                        ->orWhere(function ($sub2) use ($from) {
                            $sub2->whereNull('start_at')
                                 ->where('max_at', '>=', $from);
                        });
                });
            }

            if ($hasTo) {
                $to = $v['to'];
                $query->where(function ($sub) use ($to) {
                    $sub->where('start_at', '<=', $to)
                        ->orWhere(function ($sub2) use ($to) {
                            $sub2->whereNull('start_at')
                                 ->where('max_at', '<=', $to);
                        });
                });
            }
        }

        // Radenie: najbliĹľĹˇie dopredu (start_at alebo max_at)
        $query->orderByRaw('COALESCE(start_at, max_at) ASC');

        if ($hasFrom && $hasTo) {
            return EventResource::collection(
                $query->get()
            );
        }

        $perPage = $v['per_page'] ?? 20;

        return EventResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * GET /api/events/years
     */
    public function years()
    {
        $minYear = (int) config('events.astropixels.min_year', 2021);
        $maxYear = (int) config('events.astropixels.max_year', 2030);
        $currentYear = (int) now()->year;
        $currentYearBounded = max($minYear, min($maxYear, $currentYear));
        $defaultYear = $currentYearBounded;

        return response()->json([
            'years' => range($minYear, $maxYear),
            'defaultYear' => $defaultYear,
            'currentYearBounded' => $currentYearBounded,
            'minYear' => $minYear,
            'maxYear' => $maxYear,
        ]);
    }

    /**
     * GET /api/events/next
     */
    public function next(Request $request)
    {
        $now = CarbonImmutable::now();
        $base = $this->basePublishedQuery();

        // 1) NajbliĹľĹˇia budĂşca
        $event = (clone $base)
            ->where(function ($q) use ($now) {
                $q->where('start_at', '>=', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('start_at')
                         ->where('max_at', '>=', $now);
                  });
            })
            ->orderByRaw('COALESCE(start_at, max_at) ASC')
            ->first();

        // 2) Fallback: najbliĹľĹˇia minulĂˇ
        if (!$event) {
            $event = (clone $base)
                ->orderByRaw('COALESCE(start_at, max_at) DESC')
                ->first();
        }

        if (!$event) {
            return response()->json([
                'data' => null,
                'message' => 'No events found.',
            ]);
        }

        return new EventResource($event);
    }

    /**
     * GET /api/events/{id}
     */
    public function show(int $id)
    {
        $event = $this->basePublishedQuery()
            ->findOrFail($id);

        return new EventResource($event);
    }

    private function basePublishedQuery()
    {
        return $this->publishedEventQuery->base();
    }

    /**
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

        if (!empty($validated['type']) && is_string($validated['type']) && in_array($validated['type'], $supported, true)) {
            $types->push($validated['type']);
        }

        return $types->unique()->values()->all();
    }

    private function applyPeriodWrappers(array $validated): array
    {
        if (!empty($validated['from']) || !empty($validated['to'])) {
            return $validated;
        }

        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;
        $week = isset($validated['week']) ? (int) $validated['week'] : null;

        if ($year === null && ($month !== null || $week !== null)) {
            $minYear = (int) config('events.astropixels.min_year', 2021);
            $maxYear = (int) config('events.astropixels.max_year', 2030);
            $year = max($minYear, min($maxYear, (int) now()->year));
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
}

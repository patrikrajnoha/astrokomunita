<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use App\Services\Events\EventFilter;
use App\Services\Events\PublishedEventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
        private readonly EventFilter $eventFilter,
    ) {
    }

    /**
     * GET /api/events
     * Filtre: type, from, to, q, per_page
     */
    public function index(EventIndexRequest $request)
    {
        $filters = $this->eventFilter->normalize($request->validated());

        $feed = $filters['feed'] ?? 'all';
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

        $this->eventFilter->apply($query, $filters);

        if ($this->eventFilter->shouldBypassPagination($filters)) {
            return EventResource::collection(
                $query->get()
            );
        }

        $perPage = $filters['per_page'] ?? 20;

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
        $currentYear = (int) now((string) config('events.timezone', 'Europe/Bratislava'))->year;
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

        // 1) Nearest upcoming event.
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

        // 2) Fallback: nearest past event.
        if (!$event) {
            $event = (clone $base)
                ->orderByRaw('COALESCE(start_at, max_at) DESC')
                ->first();
        }

        if (!$event) {
            return response()->json([
                'data' => null,
                'message' => 'Nenasli sa ziadne udalosti.',
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

    /**
     * GET /api/events/lookup?ids=1,2,3
     */
    public function lookup(Request $request)
    {
        $rawIds = explode(',', (string) $request->query('ids', ''));
        $ids = collect($rawIds)
            ->map(static fn (string $id): int => (int) trim($id))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $eventsById = $this->basePublishedQuery()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        $ordered = $ids
            ->map(static fn (int $id) => $eventsById->get($id))
            ->filter()
            ->values();

        return EventResource::collection($ordered);
    }

    private function basePublishedQuery()
    {
        return $this->publishedEventQuery->base();
    }
}


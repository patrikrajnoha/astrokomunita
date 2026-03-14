<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventLiveHighlightsRequest;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use App\Services\Events\EventFilter;
use App\Services\Events\EventLiveHighlightsService;
use App\Services\Events\PublishedEventQuery;
use App\Services\Widgets\EventWidgetService;
use App\Support\EventFollowTable;
use App\Support\Sky\SkyContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
        private readonly EventFilter $eventFilter,
        private readonly EventWidgetService $eventWidgetService,
        private readonly SkyContextResolver $skyContextResolver,
        private readonly EventLiveHighlightsService $eventLiveHighlightsService,
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
        $maxYear = (int) config('events.astropixels.max_year', 2100);
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
        return response()->json($this->eventWidgetService->nextEvent());
    }

    /**
     * GET /api/events/live-highlights
     */
    public function liveHighlights(EventLiveHighlightsRequest $request): JsonResponse
    {
        $context = $this->skyContextResolver->resolve($request, $request->validated());
        $contextSource = (string) ($context['coordinate_source'] ?? 'fallback_config');

        if ($contextSource === 'fallback_config') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'location_required' => true,
                    'context_source' => $contextSource,
                    'reason' => 'location_required',
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);
        }

        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.aurora_cache_ttl_minutes',
                (int) config('observing.sky.space_weather_cache_ttl_minutes', 10)
            )
        );

        $data = Cache::remember(
            $this->liveHighlightsCacheKey($context['lat'], $context['lon'], $context['tz']),
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->eventLiveHighlightsService->build(
                $context['lat'],
                $context['lon'],
                $context['tz']
            )
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'location_required' => false,
                'context_source' => $contextSource,
                'reason' => $data === [] ? 'no_live_highlights' : null,
                'requested_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/events/{id}
     */
    public function show(Request $request, int $id)
    {
        $query = $this->basePublishedQuery()
            ->select('events.*');

        $this->applyUserFollowContext($query, $request->user()?->id);

        $event = $query
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

    private function applyUserFollowContext(Builder $query, ?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $table = EventFollowTable::resolve();
        $alias = 'event_follow_context';

        $query->leftJoin($table.' as '.$alias, function ($join) use ($alias, $userId): void {
            $join->on($alias.'.event_id', '=', 'events.id')
                ->where($alias.'.user_id', '=', $userId);
        });

        $query->addSelect($alias.'.created_at as followed_at');

        if (EventFollowTable::supportsPersonalPlanColumns($table)) {
            $query->addSelect([
                $alias.'.personal_note as personal_note',
                $alias.'.reminder_at as reminder_at',
                $alias.'.planned_time as planned_time',
                $alias.'.planned_location_label as planned_location_label',
            ]);
        }
    }

    private function liveHighlightsCacheKey(float $lat, float $lon, string $tz): string
    {
        return sprintf(
            'event_live_highlights:%s:%s:%s',
            number_format($lat, 4, '.', ''),
            number_format($lon, 4, '.', ''),
            $tz
        );
    }
}


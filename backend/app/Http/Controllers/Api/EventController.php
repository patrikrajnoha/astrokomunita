<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use App\Services\Events\EventFilter;
use App\Services\Events\PublishedEventQuery;
use App\Services\Widgets\EventWidgetService;
use App\Support\EventFollowTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
        private readonly EventFilter $eventFilter,
        private readonly EventWidgetService $eventWidgetService,
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
}


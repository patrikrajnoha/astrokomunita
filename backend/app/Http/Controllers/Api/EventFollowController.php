<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEventPlanRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Events\PublishedEventQuery;
use App\Support\EventFollowTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventFollowController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {}

    public function state(Request $request, int $id): JsonResponse
    {
        $event = $this->resolveEvent($id);
        $user = $request->user();

        return response()->json([
            'followed' => $this->followExists($user->id, $event->id),
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $event = $this->resolveEvent($id);
        $user = $request->user();
        $now = now();
        $table = EventFollowTable::resolve();

        DB::table($table)->insertOrIgnore([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'followed' => true,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $event = $this->resolveEvent($id);
        $user = $request->user();
        $table = EventFollowTable::resolve();

        DB::table($table)
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->delete();

        return response()->json([
            'followed' => false,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $table = EventFollowTable::resolve();
        $select = [$table.'.created_at as followed_at'];

        if (EventFollowTable::supportsPersonalPlanColumns($table)) {
            $select[] = $table.'.personal_note as personal_note';
            $select[] = $table.'.reminder_at as reminder_at';
            $select[] = $table.'.planned_time as planned_time';
            $select[] = $table.'.planned_location_label as planned_location_label';
        }

        $paginator = $this->publishedEventQuery
            ->base()
            ->select(array_merge(['events.*'], $select))
            ->join($table, function ($join) use ($table, $user): void {
                $join->on($table.'.event_id', '=', 'events.id')
                    ->where($table.'.user_id', '=', $user->id);
            })
            ->orderByDesc($table.'.created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => EventResource::collection($paginator->getCollection())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ]);
    }

    public function plan(UpdateEventPlanRequest $request, int $id): JsonResponse
    {
        $event = $this->resolveEvent($id);
        $user = $request->user();
        $table = EventFollowTable::resolve();

        if (! EventFollowTable::supportsPersonalPlanColumns($table)) {
            return response()->json([
                'message' => 'Personal plan data are unavailable in this environment.',
            ], 409);
        }

        $now = now();

        DB::table($table)->insertOrIgnore([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $validated = $request->validated();

        DB::table($table)
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->update([
                'personal_note' => $this->toNullableString($validated['personal_note'] ?? null),
                'reminder_at' => $this->normalizeDateTime($validated['reminder_at'] ?? null),
                'planned_time' => $this->normalizeDateTime($validated['planned_time'] ?? null),
                'planned_location_label' => $this->toNullableString($validated['planned_location_label'] ?? null),
                'updated_at' => now(),
            ]);

        $resourceEvent = $this->publishedEventQuery
            ->base()
            ->select([
                'events.*',
                $table.'.created_at as followed_at',
                $table.'.personal_note as personal_note',
                $table.'.reminder_at as reminder_at',
                $table.'.planned_time as planned_time',
                $table.'.planned_location_label as planned_location_label',
            ])
            ->join($table, function ($join) use ($table, $user, $event): void {
                $join->on($table.'.event_id', '=', 'events.id')
                    ->where($table.'.user_id', '=', $user->id)
                    ->where($table.'.event_id', '=', $event->id);
            })
            ->firstOrFail();

        return response()->json([
            'followed' => true,
            'data' => (new EventResource($resourceEvent))->resolve(),
        ]);
    }

    private function resolveEvent(int $id): Event
    {
        return $this->publishedEventQuery->base()->findOrFail($id);
    }

    private function followExists(int $userId, int $eventId): bool
    {
        return DB::table(EventFollowTable::resolve())
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->exists();
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

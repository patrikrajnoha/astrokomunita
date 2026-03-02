<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Events\PublishedEventQuery;
use App\Support\EventFollowTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $paginator = $this->publishedEventQuery
            ->base()
            ->select(['events.*', $table.'.created_at as followed_at'])
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
}

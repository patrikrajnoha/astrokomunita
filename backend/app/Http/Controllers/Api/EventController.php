<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/events
     * Filtre: type, from, to, q, per_page
     */
    public function index(EventIndexRequest $request)
    {
        $v = $request->validated();

        $query = Event::query()
            ->where('visibility', 1)
            ->published()
            // ✅ iba eventy, ktoré vznikli zo schválených kandidátov
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('event_candidates')
                    ->whereColumn('event_candidates.published_event_id', 'events.id')
                    ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
            });

        // Filter: type
        if (!empty($v['type'])) {
            $query->where('type', $v['type']);
        }

        // Filter: fulltext (title/short/description)
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
         * Primárne podľa start_at, fallback na max_at.
         */
        if (!empty($v['from'])) {
            $from = $v['from'];
            $query->where(function ($sub) use ($from) {
                $sub->where('start_at', '>=', $from)
                    ->orWhere(function ($sub2) use ($from) {
                        $sub2->whereNull('start_at')
                             ->where('max_at', '>=', $from);
                    });
            });
        }

        if (!empty($v['to'])) {
            $to = $v['to'];
            $query->where(function ($sub) use ($to) {
                $sub->where('start_at', '<=', $to)
                    ->orWhere(function ($sub2) use ($to) {
                        $sub2->whereNull('start_at')
                             ->where('max_at', '<=', $to);
                    });
            });
        }

        // Radenie: najbližšie dopredu (start_at alebo max_at)
        $query->orderByRaw('COALESCE(start_at, max_at) ASC');

        $perPage = $v['per_page'] ?? 20;

        return EventResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * GET /api/events/next
     */
    public function next(Request $request)
    {
        $now = CarbonImmutable::now();

        $base = Event::query()
            ->where('visibility', 1)
            ->published()
            // ✅ iba eventy zo schválených kandidátov
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('event_candidates')
                    ->whereColumn('event_candidates.published_event_id', 'events.id')
                    ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
            });

        // 1) Najbližšia budúca
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

        // 2) Fallback: najbližšia minulá
        if (!$event) {
            $event = (clone $base)
                ->orderByRaw('COALESCE(start_at, max_at) DESC')
                ->first();
        }

        if (!$event) {
            return response()->json([
                'message' => 'No events found.',
            ], 404);
        }

        return new EventResource($event);
    }

    /**
     * GET /api/events/{id}
     */
    public function show(int $id)
    {
        $event = Event::query()
            ->where('visibility', 1)
            ->published()
            // ✅ iba eventy zo schválených kandidátov
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('event_candidates')
                    ->whereColumn('event_candidates.published_event_id', 'events.id')
                    ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
            })
            ->findOrFail($id);

        return new EventResource($event);
    }
}

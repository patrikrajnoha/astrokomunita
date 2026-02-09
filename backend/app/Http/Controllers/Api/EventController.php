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
            ->where(function ($sub) {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($q) {
                        $q->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
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
     * GET /api/events/next
     */
    public function next(Request $request)
    {
        $now = CarbonImmutable::now();

        $base = Event::query()
            ->where('visibility', 1)
            ->published()
            ->where(function ($sub) {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($q) {
                        $q->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
            });

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
        $event = Event::query()
            ->where('visibility', 1)
            ->published()
            ->where(function ($sub) {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($q) {
                        $q->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
            })
            ->findOrFail($id);

        return new EventResource($event);
    }
}

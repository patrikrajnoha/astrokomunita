<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\EventIcsService;
use Illuminate\Http\Response;

class EventCalendarController extends Controller
{
    public function __construct(
        private readonly EventIcsService $icsService,
    ) {
    }

    /**
     * GET /api/events/{event}/ics
     */
    public function show(int $id): Response
    {
        return $this->downloadEventIcs($id);
    }

    /**
     * GET /api/events/{event}/calendar.ics
     */
    public function showCalendarIcs(int $id): Response
    {
        return $this->downloadEventIcs($id);
    }

    private function downloadEventIcs(int $id): Response
    {
        $event = Event::query()
            ->where('visibility', 1)
            ->published()
            ->where(function ($sub): void {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($approved): void {
                        $approved->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
            })
            ->findOrFail($id);

        if (! $event->start_at && ! $event->max_at) {
            abort(404);
        }

        $ics = $this->icsService->buildSingleEventIcs($event);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-' . $event->id . '.ics"',
        ]);
    }
}

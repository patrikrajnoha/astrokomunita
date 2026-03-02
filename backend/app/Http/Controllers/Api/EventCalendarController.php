<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventIcsService;
use App\Services\Events\PublishedEventQuery;
use Illuminate\Http\Response;

class EventCalendarController extends Controller
{
    public function __construct(
        private readonly EventIcsService $icsService,
        private readonly PublishedEventQuery $publishedEventQuery,
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
        $event = $this->publishedEventQuery->base()
            ->findOrFail($id);

        if (! $event->start_at && ! $event->max_at) {
            abort(404);
        }

        $ics = $this->icsService->buildSingleEventIcs($event);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="astrokomunita-event-' . $event->id . '.ics"',
        ]);
    }
}

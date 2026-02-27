<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventIcsService;
use App\Services\FeaturedEventsResolver;
use App\Services\MarkYourCalendarPopupService;
use Illuminate\Http\Response;

class FeaturedEventsCalendarController extends Controller
{
    public function __construct(
        private readonly FeaturedEventsResolver $resolver,
        private readonly EventIcsService $icsService,
        private readonly MarkYourCalendarPopupService $popupService,
    ) {
    }

    /**
     * GET /api/featured-events/{month}/calendar.ics
     */
    public function showBundle(string $month): Response
    {
        $monthKey = $this->popupService->resolveMonthKey($month);
        $resolved = $this->resolver->resolveForMonth($monthKey);

        $ids = collect($resolved['events'])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        $eventsById = Event::query()
            ->whereIn('id', $ids)
            ->get(['id', 'title', 'start_at', 'end_at', 'max_at', 'short', 'description'])
            ->keyBy('id');

        $events = $ids
            ->map(fn (int $id) => $eventsById->get($id))
            ->filter()
            ->values();

        $ics = $this->icsService->buildBundleIcs($events, 'Featured events ' . $monthKey);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="featured-events-' . $monthKey . '.ics"',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventReminderResource;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventReminder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventReminderController extends Controller
{
    /**
     * POST /api/events/{event}/reminders
     */
    public function store(Request $request, Event $event)
    {
        $isApproved = EventCandidate::query()
            ->where('published_event_id', $event->id)
            ->where('status', EventCandidate::STATUS_APPROVED)
            ->exists();
        if (!$event->visibility || !$event->source_name || !$event->source_uid || !$isApproved) {
            abort(404);
        }

        $validated = $request->validate([
            'minutes_before' => ['required', 'integer', Rule::in([15, 60, 1440])],
        ]);

        $start = $event->start_at ?? $event->max_at;
        if (!$start) {
            return response()->json([
                'message' => 'Udalosť nemá platný začiatok.',
            ], 422);
        }

        $remindAt = CarbonImmutable::parse($start)->subMinutes($validated['minutes_before']);
        if ($remindAt->isPast()) {
            return response()->json([
                'message' => 'Upozornenie je v minulosti.',
            ], 422);
        }

        $reminder = EventReminder::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'event_id' => $event->id,
            ],
            [
                'minutes_before' => $validated['minutes_before'],
                'remind_at' => $remindAt,
                'status' => 'pending',
                'sent_at' => null,
            ]
        );

        return (new EventReminderResource($reminder->load('event')))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * GET /api/me/reminders
     */
    public function index(Request $request)
    {
        $items = EventReminder::query()
            ->where('user_id', $request->user()->id)
            ->with('event')
            ->orderBy('remind_at')
            ->get();

        return EventReminderResource::collection($items);
    }

    /**
     * DELETE /api/reminders/{reminder}
     */
    public function destroy(Request $request, EventReminder $reminder)
    {
        if ($reminder->user_id !== $request->user()->id) {
            abort(403);
        }

        $reminder->delete();

        return response()->json(['ok' => true]);
    }
}

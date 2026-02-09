<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCandidate;
use App\Services\Events\EventCandidatePublisher;
use Illuminate\Http\Request;
use RuntimeException;

class EventCandidateReviewController extends Controller
{
    public function __construct(
        private readonly EventCandidatePublisher $publisher,
    ) {
    }

    public function approve(Request $request, EventCandidate $candidate)
    {
        if ($candidate->status !== EventCandidate::STATUS_PENDING) {
            return response()->json([
                'ok' => false,
                'message' => 'Candidate is not pending.',
                'status' => $candidate->status,
            ], 409);
        }

        $event = $this->publisher->approve($candidate, (int) $request->user()->id);

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
            'published_event_id' => $event->id,
        ]);
    }

    public function reject(Request $request, EventCandidate $candidate)
    {
        if ($candidate->status !== EventCandidate::STATUS_PENDING) {
            return response()->json([
                'ok' => false,
                'message' => 'Candidate is not pending.',
                'status' => $candidate->status,
            ], 409);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->publisher->reject(
                $candidate,
                (int) $request->user()->id,
                (string) ($validated['reason'] ?? '')
            );
        } catch (RuntimeException) {
            return response()->json([
                'ok' => false,
                'message' => 'Candidate is not pending.',
                'status' => $candidate->fresh()->status,
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
        ]);
    }
}

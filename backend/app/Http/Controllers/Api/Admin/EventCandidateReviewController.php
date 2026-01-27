<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventCandidateReviewController extends Controller
{
    public function approve(Request $request, EventCandidate $candidate)
    {
        if ($candidate->status !== EventCandidate::STATUS_PENDING) {
            return response()->json([
                'ok' => false,
                'message' => 'Candidate is not pending.',
                'status' => $candidate->status,
            ], 409);
        }

        return DB::transaction(function () use ($request, $candidate) {

            // 1) Create (or update) published Event
            $event = $candidate->published_event_id
                ? Event::find($candidate->published_event_id)
                : null;

            if (!$event) {
                $event = new Event();
            }

            $event->forceFill([
                'title'       => $candidate->title,
                'type'        => $candidate->type,

                'start_at'    => $candidate->start_at,
                'end_at'      => $candidate->end_at,
                'max_at'      => $candidate->max_at,

                'short'       => $candidate->short,
                'description' => $candidate->description,

                // MVP: publish = visible
                'visibility'  => 1,

                // Publish scope relies on source_name + source_uid
                'source_name' => $candidate->source_name,
                'source_uid'  => $candidate->source_uid,
                'source_hash' => $candidate->source_hash,
            ])->save();

            // 2) Mark candidate as approved + link to published event
            $candidate->forceFill([
                'status'             => EventCandidate::STATUS_APPROVED,
                'published_event_id' => $event->id,
                'reviewed_by'        => $request->user()?->id,
                'reviewed_at'        => now(),
                'reject_reason'      => null,
            ])->save();

            return response()->json([
                'ok' => true,
                'candidate' => $candidate->fresh(),
                'published_event_id' => $event->id,
            ]);
        });
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

        $candidate->forceFill([
            'status'        => EventCandidate::STATUS_REJECTED,
            'reviewed_by'   => $request->user()?->id,
            'reviewed_at'   => now(),
            'reject_reason' => $validated['reason'] ?? null,
        ])->save();

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
        ]);
    }
}

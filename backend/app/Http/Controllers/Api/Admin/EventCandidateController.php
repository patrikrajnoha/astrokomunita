<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCandidate;
use Illuminate\Http\Request;

class EventCandidateController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', EventCandidate::STATUS_PENDING);

        $items = EventCandidate::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('max_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(EventCandidate $eventCandidate)
    {
        return response()->json($eventCandidate);
    }
}

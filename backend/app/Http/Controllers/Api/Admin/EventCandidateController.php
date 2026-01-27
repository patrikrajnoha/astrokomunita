<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCandidate;
use Illuminate\Http\Request;

class EventCandidateController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status'      => ['nullable', 'string', 'max:50'],
            'type'        => ['nullable', 'string', 'max:100'],
            'raw_type'    => ['nullable', 'string', 'max:100'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'q'           => ['nullable', 'string', 'max:200'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $status     = $validated['status'] ?? EventCandidate::STATUS_PENDING; // default pending
        $type       = $validated['type'] ?? null;
        $rawType    = $validated['raw_type'] ?? null;
        $sourceName = $validated['source_name'] ?? null;
        $q          = isset($validated['q']) ? trim($validated['q']) : null;
        $perPage    = $validated['per_page'] ?? 20;

        $items = EventCandidate::query()
            ->select([
                'id',
                'source_name',
                'source_url',
                'title',
                'status',
                'raw_type',
                'type',
                'max_at',
                'start_at',
                'end_at',
                'short',
                'description',
                'reviewed_by',
                'reviewed_at',
                'reject_reason',
                'created_at',
                'updated_at',
            ])
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($type, fn ($qq) => $qq->where('type', $type))
            ->when($rawType, fn ($qq) => $qq->where('raw_type', $rawType))
            ->when($sourceName, fn ($qq) => $qq->where('source_name', $sourceName))
            ->when($q !== null && $q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

                $qq->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('short', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderByDesc('max_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($items);
    }

    public function show(EventCandidate $eventCandidate)
    {
        return response()->json($eventCandidate);
    }
}

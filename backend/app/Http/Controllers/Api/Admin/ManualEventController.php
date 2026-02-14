<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\ManualEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManualEventController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $query = ManualEvent::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('event_type', $request->query('type'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where('title', 'like', "%{$q}%");
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $this->validateManual($request);

        $manual = ManualEvent::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'visibility' => $validated['visibility'] ?? 1,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json($manual, 201);
    }

    public function update(Request $request, ManualEvent $manualEvent)
    {
        $validated = $this->validateManual($request);

        $manualEvent->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'visibility' => $validated['visibility'] ?? $manualEvent->visibility,
        ]);
        $manualEvent->save();

        return response()->json($manualEvent);
    }

    public function destroy(ManualEvent $manualEvent)
    {
        $manualEvent->delete();
        return response()->noContent();
    }

    public function publish(ManualEvent $manualEvent)
    {
        if ($manualEvent->status === 'published' && $manualEvent->published_event_id) {
            return response()->json([
                'message' => 'Already published.',
                'event_id' => $manualEvent->published_event_id,
            ], 409);
        }

        $event = new Event();
        $event->title = $manualEvent->title;
        $event->description = $manualEvent->description;
        $event->type = $manualEvent->event_type;
        $event->start_at = $manualEvent->starts_at;
        $event->end_at = $manualEvent->ends_at;
        $event->max_at = $manualEvent->starts_at;
        $event->visibility = $manualEvent->visibility ?? 1;
        $event->source_name = 'manual';
        $event->source_uid = (string) Str::uuid();
        $event->save();

        $manualEvent->status = 'published';
        $manualEvent->published_event_id = $event->id;
        $manualEvent->save();

        return new EventResource($event);
    }

    private function validateManual(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_type' => ['required', 'string', 'in:' . implode(',', EventType::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'integer'],
        ]);
    }
}

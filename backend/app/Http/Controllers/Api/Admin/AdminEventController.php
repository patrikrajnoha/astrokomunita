<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminEventController extends Controller
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

        $events = Event::query()
            ->orderByRaw('COALESCE(start_at, max_at) DESC')
            ->paginate($perPage);

        return EventResource::collection($events);
    }

    public function show(Event $event)
    {
        return new EventResource($event);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);

        $event = new Event();
        $event->title = $validated['title'];
        $event->description = $validated['description'] ?? null;
        $event->type = $validated['type'];
        $event->region_scope = $validated['region_scope'] ?? RegionScope::Global->value;
        $event->start_at = $validated['start_at'];
        $event->end_at = $validated['end_at'] ?? null;
        $event->visibility = $validated['visibility'];
        $event->source_name = 'manual';
        $event->source_uid = (string) Str::uuid();
        $event->max_at = $event->start_at;
        $event->save();

        return new EventResource($event);
    }

    public function update(Request $request, Event $event)
    {
        $validated = $this->validateEvent($request);

        $event->title = $validated['title'];
        $event->description = $validated['description'] ?? null;
        $event->type = $validated['type'];
        $event->region_scope = $validated['region_scope'] ?? $event->region_scope ?? RegionScope::Global->value;
        $event->start_at = $validated['start_at'];
        $event->end_at = $validated['end_at'] ?? null;
        $event->visibility = $validated['visibility'];
        $event->max_at = $event->start_at;
        $event->save();

        return new EventResource($event);
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(EventType::values())],
            'region_scope' => ['nullable', 'string', Rule::in(RegionScope::values())],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date'],
            'visibility' => ['required', Rule::in([0, 1])],
        ]);
    }
}

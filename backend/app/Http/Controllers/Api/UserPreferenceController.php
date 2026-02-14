<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPreferencesRequest;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preferences = $request->user()->eventPreference;

        return response()->json([
            'data' => [
                'event_types' => $preferences?->normalizedEventTypes() ?? [],
                'region' => $preferences?->regionEnum()->value ?? RegionScope::Global->value,
                'has_preferences' => (bool) $preferences,
                'updated_at' => optional($preferences?->updated_at)?->toIso8601String(),
            ],
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
            ],
        ]);
    }

    public function update(UpdateUserPreferencesRequest $request)
    {
        $validated = $request->validated();

        $eventTypes = collect($validated['event_types'] ?? [])
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        $preferences = $request->user()->eventPreference()->updateOrCreate(
            [],
            [
                'event_types' => $eventTypes,
                'region' => $validated['region'],
            ]
        );

        return response()->json([
            'data' => [
                'event_types' => $preferences->normalizedEventTypes(),
                'region' => $preferences->regionEnum()->value,
                'has_preferences' => true,
                'updated_at' => optional($preferences->updated_at)?->toIso8601String(),
            ],
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
            ],
        ]);
    }
}

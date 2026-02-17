<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPreferencesRequest;
use App\Models\UserPreference;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preferences = $request->user()->eventPreference;

        return response()->json([
            'data' => [
                'event_types' => $preferences?->normalizedEventTypes() ?? [],
                'interests' => $preferences?->normalizedInterests() ?? [],
                'region' => $preferences?->regionEnum()->value ?? RegionScope::Global->value,
                'location_label' => $preferences?->location_label,
                'location_place_id' => $preferences?->location_place_id,
                'location_lat' => $preferences?->location_lat,
                'location_lon' => $preferences?->location_lon,
                'onboarding_completed_at' => $preferences?->onboardingCompletedAtIso(),
                'has_preferences' => (bool) $preferences,
                'updated_at' => optional($preferences?->updated_at)?->toIso8601String(),
            ],
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
                'supported_interests' => config('onboarding.interests', []),
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

        $interests = collect($validated['interests'] ?? [])
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        /** @var UserPreference $preferences */
        $preferences = $request->user()->eventPreference()->firstOrNew([]);

        if (array_key_exists('event_types', $validated)) {
            $preferences->event_types = $eventTypes;
        }

        if (array_key_exists('interests', $validated)) {
            $preferences->interests = $interests;
        }

        if (array_key_exists('region', $validated) && is_string($validated['region'])) {
            $preferences->region = $validated['region'];
        } elseif (! is_string($preferences->region) || $preferences->region === '') {
            $preferences->region = RegionScope::Global->value;
        }

        if (array_key_exists('location_label', $validated)) {
            $preferences->location_label = $validated['location_label'];
        }

        if (array_key_exists('location_place_id', $validated)) {
            $preferences->location_place_id = $validated['location_place_id'];
        }

        if (array_key_exists('location_lat', $validated)) {
            $preferences->location_lat = $validated['location_lat'];
        }

        if (array_key_exists('location_lon', $validated)) {
            $preferences->location_lon = $validated['location_lon'];
        }

        if (array_key_exists('onboarding_completed_at', $validated)) {
            $preferences->onboarding_completed_at = $validated['onboarding_completed_at']
                ? Carbon::parse((string) $validated['onboarding_completed_at'])
                : null;
        }

        $preferences->save();

        return response()->json([
            'data' => [
                'event_types' => $preferences->normalizedEventTypes(),
                'interests' => $preferences->normalizedInterests(),
                'region' => $preferences->regionEnum()->value,
                'location_label' => $preferences->location_label,
                'location_place_id' => $preferences->location_place_id,
                'location_lat' => $preferences->location_lat,
                'location_lon' => $preferences->location_lon,
                'onboarding_completed_at' => $preferences->onboardingCompletedAtIso(),
                'has_preferences' => true,
                'updated_at' => optional($preferences->updated_at)?->toIso8601String(),
            ],
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
                'supported_interests' => config('onboarding.interests', []),
            ],
        ]);
    }
}

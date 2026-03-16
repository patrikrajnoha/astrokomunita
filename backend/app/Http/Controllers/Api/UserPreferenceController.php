<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPreferencesRequest;
use App\Models\UserPreference;
use App\Support\SidebarSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $preferences = $user->eventPreference;

        return response()->json([
            'data' => $this->preferencesPayload($preferences),
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
                'supported_interests' => config('onboarding.interests', []),
                'supported_sidebar_widgets' => $this->supportedSidebarWidgetsPayload(),
                'supported_sidebar_scopes' => SidebarSectionRegistry::scopes(),
            ],
        ]);
    }

    public function update(UpdateUserPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

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
        $sidebarWidgetKeys = collect($validated['sidebar_widget_keys'] ?? [])
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value) => trim($value))
            ->unique()
            ->take(3)
            ->values()
            ->all();
        $sidebarWidgetOverrides = collect($validated['sidebar_widget_overrides'] ?? [])
            ->mapWithKeys(function ($value, $scope) {
                if (!is_string($scope) || !is_array($value) || !SidebarSectionRegistry::isValidScope($scope)) {
                    return [];
                }

                $keys = collect($value)
                    ->filter(static fn ($item) => is_string($item) && trim($item) !== '')
                    ->map(static fn (string $item) => trim($item))
                    ->unique()
                    ->take(3)
                    ->values()
                    ->all();

                return [$scope => $keys];
            })
            ->all();

        /** @var UserPreference $preferences */
        $preferences = $user->eventPreference()->firstOrNew([]);

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

        if (array_key_exists('bortle_class', $validated)) {
            $preferences->bortle_class = $validated['bortle_class'];
        } elseif (!is_numeric($preferences->bortle_class)) {
            $preferences->bortle_class = UserPreference::DEFAULT_BORTLE_CLASS;
        }

        if (array_key_exists('sidebar_widget_overrides', $validated)) {
            $preferences->sidebar_widget_keys = $sidebarWidgetOverrides;
        } elseif (array_key_exists('sidebar_widget_keys', $validated)) {
            $preferences->sidebar_widget_keys = $sidebarWidgetKeys;
        }

        $preferences->save();

        return response()->json([
            'data' => $this->preferencesPayload($preferences),
            'meta' => [
                'supported_event_types' => EventType::values(),
                'supported_regions' => RegionScope::values(),
                'supported_interests' => config('onboarding.interests', []),
                'supported_sidebar_widgets' => $this->supportedSidebarWidgetsPayload(),
                'supported_sidebar_scopes' => SidebarSectionRegistry::scopes(),
            ],
        ]);
    }

    /**
     * @return array<int, array{section_key:string,title:string}>
     */
    private function supportedSidebarWidgetsPayload(): array
    {
        return array_values(array_map(
            static fn (array $section): array => [
                'section_key' => (string) ($section['section_key'] ?? ''),
                'title' => (string) ($section['title'] ?? ''),
            ],
            SidebarSectionRegistry::sections(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function preferencesPayload(?UserPreference $preferences): array
    {
        $resolvedPreferences = $preferences ?? new UserPreference();

        return [
            'event_types' => $resolvedPreferences->normalizedEventTypes(),
            'interests' => $resolvedPreferences->normalizedInterests(),
            'region' => $resolvedPreferences->regionEnum()->value,
            'location_label' => $resolvedPreferences->location_label,
            'location_place_id' => $resolvedPreferences->location_place_id,
            'location_lat' => $resolvedPreferences->location_lat,
            'location_lon' => $resolvedPreferences->location_lon,
            'onboarding_completed_at' => $resolvedPreferences->onboardingCompletedAtIso(),
            'bortle_class' => $resolvedPreferences->resolvedBortleClass(),
            'sidebar_widget_keys' => $resolvedPreferences->resolvedSidebarWidgetKeys(),
            'sidebar_widget_overrides' => $resolvedPreferences->resolvedSidebarWidgetOverrides(),
            'has_preferences' => (bool) $preferences,
            'updated_at' => optional($preferences?->updated_at)?->toIso8601String(),
        ];
    }
}

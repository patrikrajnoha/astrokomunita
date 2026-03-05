<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location_label' => ['nullable', 'string', 'max:80'],
            'location_source' => ['nullable', 'string', Rule::in(['preset', 'gps', 'manual'])],
            'location' => ['nullable', 'string', 'max:60'], // legacy payload compatibility
        ]);

        $timezone = $this->sanitizeTimezone($validated['timezone'] ?? null);
        if ($timezone === null) {
            throw ValidationException::withMessages([
                'timezone' => ['Timezone must be a valid IANA identifier.'],
            ]);
        }

        $user = $request->user();
        $user->latitude = round((float) $validated['latitude'], 7);
        $user->longitude = round((float) $validated['longitude'], 7);
        $user->timezone = $timezone;

        $label = $this->normalizeLabel(
            $validated['location_label']
                ?? $validated['location']
                ?? ($this->supportsLocationLabelColumn() ? $user->location_label : null)
                ?? $user->location
                ?? null
        );
        $source = $this->normalizeSource($validated['location_source'] ?? null) ?? 'manual';

        if ($this->supportsLocationLabelColumn()) {
            $user->location_label = $label;
        }

        if ($this->supportsLocationSourceColumn()) {
            $user->location_source = $source;
        }

        $user->location = $label !== null ? Str::substr($label, 0, 60) : null;

        $user->save();

        return response()->json($user->fresh());
    }

    private function sanitizeTimezone(mixed $raw): ?string
    {
        $candidate = is_string($raw) ? trim($raw) : '';
        if ($candidate === '') {
            $candidate = (string) config('observing.default_timezone', 'Europe/Bratislava');
        }

        return in_array($candidate, timezone_identifiers_list(), true) ? $candidate : null;
    }

    private function normalizeLabel(mixed $raw): ?string
    {
        $label = is_string($raw) ? trim($raw) : '';
        return $label !== '' ? Str::substr($label, 0, 80) : null;
    }

    private function normalizeSource(mixed $raw): ?string
    {
        $source = strtolower(trim((string) $raw));
        return in_array($source, ['preset', 'gps', 'manual'], true) ? $source : null;
    }

    private function supportsLocationLabelColumn(): bool
    {
        static $hasColumn;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('users', 'location_label');
        }

        return $hasColumn;
    }

    private function supportsLocationSourceColumn(): bool
    {
        static $hasColumn;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('users', 'location_source');
        }

        return $hasColumn;
    }
}

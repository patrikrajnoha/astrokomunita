<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:60'],
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

        if (array_key_exists('location', $validated)) {
            $user->location = trim((string) ($validated['location'] ?? '')) ?: null;
        }

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
}

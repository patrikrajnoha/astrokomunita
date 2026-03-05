<?php

namespace App\Services\Location;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserLocationService
{
    /**
     * @param array<string,mixed> $payload
     */
    public function update(User $user, array $payload, bool $allowLabelOnly = false): User
    {
        $hasLatitude = array_key_exists('latitude', $payload);
        $hasLongitude = array_key_exists('longitude', $payload);

        if ($hasLatitude || $hasLongitude) {
            if (! $hasLatitude || ! $hasLongitude) {
                throw ValidationException::withMessages([
                    'latitude' => ['Latitude and longitude must be provided together.'],
                    'longitude' => ['Latitude and longitude must be provided together.'],
                ]);
            }

            $latitude = round((float) $payload['latitude'], 7);
            $longitude = round((float) $payload['longitude'], 7);
            $timezone = $this->sanitizeTimezone($payload['timezone'] ?? null);
            if ($timezone === null) {
                throw ValidationException::withMessages([
                    'timezone' => ['Timezone must be a valid IANA identifier.'],
                ]);
            }

            $label = $this->normalizeLabel(
                $payload['location_label']
                    ?? $payload['location']
                    ?? ($this->supportsLocationLabelColumn() ? $user->location_label : null)
                    ?? $user->location
                    ?? null
            );
            $source = $this->normalizeSource($payload['location_source'] ?? null) ?? 'manual';

            $this->applyCanonicalLocation($user, $label, $latitude, $longitude, $timezone, $source);

            return $user->fresh();
        }

        if (! $allowLabelOnly) {
            throw ValidationException::withMessages([
                'latitude' => ['Latitude is required.'],
                'longitude' => ['Longitude is required.'],
            ]);
        }

        if (! array_key_exists('location', $payload) && ! array_key_exists('location_label', $payload)) {
            return $user->fresh();
        }

        $nextLabel = $this->normalizeLabel($payload['location_label'] ?? $payload['location'] ?? null);
        $currentLabel = $this->normalizeLabel(
            ($this->supportsLocationLabelColumn() ? $user->location_label : null)
                ?? $user->location
                ?? null
        );

        if (! $this->labelsDiffer($currentLabel, $nextLabel)) {
            $this->syncLabelOnly($user, $nextLabel);

            return $user->fresh();
        }

        if ($nextLabel === null) {
            $this->applyCanonicalLocation($user, null, null, null, null, null);

            return $user->fresh();
        }

        $mappedPreset = $this->resolveMappedPreset($nextLabel);
        if ($mappedPreset !== null) {
            $source = $this->normalizeSource($payload['location_source'] ?? null) ?? 'preset';
            $this->applyCanonicalLocation(
                $user,
                $nextLabel,
                $mappedPreset['lat'],
                $mappedPreset['lon'],
                $mappedPreset['tz'],
                $source
            );

            return $user->fresh();
        }

        // Unknown label: keep text but clear stale canonical coordinates.
        $this->applyCanonicalLocation($user, $nextLabel, null, null, null, null);

        return $user->fresh();
    }

    /**
     * @return array{lat:float,lon:float,tz:string}|null
     */
    private function resolveMappedPreset(string $label): ?array
    {
        $map = config('user_locations.map', []);

        if (isset($map[$label]) && is_array($map[$label])) {
            return $this->normalizePreset($map[$label]);
        }

        $normalizedMap = [];
        foreach ($map as $name => $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $normalizedName = $this->normalizeLookupKey((string) $name);
            if ($normalizedName !== '') {
                $normalizedMap[$normalizedName] = $candidate;
            }
        }

        foreach ($this->locationLookupCandidates($label) as $lookupKey) {
            if (isset($normalizedMap[$lookupKey])) {
                return $this->normalizePreset($normalizedMap[$lookupKey]);
            }

            foreach ($normalizedMap as $knownName => $knownPreset) {
                if (str_starts_with($lookupKey, $knownName.' ')) {
                    return $this->normalizePreset($knownPreset);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $preset
     * @return array{lat:float,lon:float,tz:string}|null
     */
    private function normalizePreset(array $preset): ?array
    {
        $lat = isset($preset['lat']) && is_numeric($preset['lat']) ? (float) $preset['lat'] : null;
        $lon = isset($preset['lon']) && is_numeric($preset['lon']) ? (float) $preset['lon'] : null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $timezone = $this->sanitizeTimezone($preset['tz'] ?? null);
        if ($timezone === null) {
            $timezone = $this->sanitizeTimezone(config('user_locations.fallback_timezone', 'Europe/Bratislava'));
        }

        if ($timezone === null) {
            $timezone = 'Europe/Bratislava';
        }

        return [
            'lat' => round($lat, 7),
            'lon' => round($lon, 7),
            'tz' => $timezone,
        ];
    }

    /**
     * @return list<string>
     */
    private function locationLookupCandidates(string $rawLocation): array
    {
        $candidates = [];
        $normalized = $this->normalizeLookupKey($rawLocation);
        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        $withoutCountrySuffix = preg_replace('/\s*,\s*(sk|slovakia|slovensko|cz|czechia|czech republic)\s*$/i', '', $rawLocation);
        $withoutCountrySuffix = is_string($withoutCountrySuffix) ? $withoutCountrySuffix : $rawLocation;
        $normalizedWithoutCountry = $this->normalizeLookupKey($withoutCountrySuffix);
        if ($normalizedWithoutCountry !== '' && ! in_array($normalizedWithoutCountry, $candidates, true)) {
            $candidates[] = $normalizedWithoutCountry;
        }

        $beforeComma = trim((string) Str::of($rawLocation)->before(','));
        $normalizedBeforeComma = $this->normalizeLookupKey($beforeComma);
        if ($normalizedBeforeComma !== '' && ! in_array($normalizedBeforeComma, $candidates, true)) {
            $candidates[] = $normalizedBeforeComma;
        }

        return $candidates;
    }

    private function normalizeLookupKey(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->value();
        $clean = preg_replace('/[^a-z0-9]+/i', ' ', $ascii);
        $clean = is_string($clean) ? trim(preg_replace('/\s+/', ' ', $clean) ?? '') : '';

        return $clean;
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

    private function labelsDiffer(?string $before, ?string $after): bool
    {
        if ($before === null && $after === null) {
            return false;
        }

        if ($before === null || $after === null) {
            return true;
        }

        return mb_strtolower($before) !== mb_strtolower($after);
    }

    private function syncLabelOnly(User $user, ?string $label): void
    {
        if ($this->supportsLocationLabelColumn()) {
            $user->location_label = $label;
        }

        $user->location = $label !== null ? Str::substr($label, 0, 60) : null;
        $user->save();
    }

    private function applyCanonicalLocation(
        User $user,
        ?string $label,
        ?float $latitude,
        ?float $longitude,
        ?string $timezone,
        ?string $source
    ): void {
        $user->latitude = $latitude;
        $user->longitude = $longitude;
        $user->timezone = $timezone;

        if ($this->supportsLocationLabelColumn()) {
            $user->location_label = $label;
        }

        if ($this->supportsLocationSourceColumn()) {
            $user->location_source = $source;
        }

        $user->location = $label !== null ? Str::substr($label, 0, 60) : null;
        $user->save();
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

<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preferences = $this->resource->relationLoaded('eventPreference')
            ? $this->eventPreference
            : null;

        $location = null;
        if ($this->latitude !== null || $this->longitude !== null || !empty($this->timezone) || !empty($this->location_label)) {
            $location = [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'timezone' => $this->timezone,
                'label' => $this->location_label,
                'source' => $this->location_source,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => optional($this->email_verified_at)?->toIso8601String(),
            'date_of_birth' => optional($this->date_of_birth)?->toDateString(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'location' => $location,
            'preferences' => [
                'event_types' => $preferences?->normalizedEventTypes() ?? [],
                'interests' => $preferences?->normalizedInterests() ?? [],
                'region' => $preferences?->regionEnum()->value,
                'location_label' => $preferences?->location_label,
                'location_place_id' => $preferences?->location_place_id,
                'location_lat' => $preferences?->location_lat,
                'location_lon' => $preferences?->location_lon,
                'onboarding_completed_at' => $preferences?->onboardingCompletedAtIso(),
                'bortle_class' => $preferences?->resolvedBortleClass(),
            ],
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\RegionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserPreference extends Model
{
    public const DEFAULT_BORTLE_CLASS = 6;

    protected $fillable = [
        'user_id',
        'event_types',
        'interests',
        'region',
        'location_label',
        'location_place_id',
        'location_lat',
        'location_lon',
        'onboarding_completed_at',
        'bortle_class',
    ];

    protected $casts = [
        'event_types' => 'array',
        'interests' => 'array',
        'location_lat' => 'float',
        'location_lon' => 'float',
        'onboarding_completed_at' => 'datetime',
        'bortle_class' => 'integer',
    ];

    public function resolvedBortleClass(): int
    {
        $value = is_numeric($this->bortle_class) ? (int) $this->bortle_class : self::DEFAULT_BORTLE_CLASS;

        return max(1, min(9, $value));
    }

    /**
     * @return list<string>
     */
    public function normalizedEventTypes(): array
    {
        $supported = EventType::values();
        $types = collect($this->event_types ?? [])
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        if ($types === []) {
            return [];
        }

        return collect($types)
            ->unique()
            ->filter(static fn (string $type) => in_array($type, $supported, true))
            ->values()
            ->all();
    }

    public function regionEnum(): RegionScope
    {
        return RegionScope::tryFrom((string) $this->region) ?? RegionScope::Global;
    }

    /**
     * @return list<string>
     */
    public function normalizedInterests(): array
    {
        $supported = collect(config('onboarding.interests', []))
            ->pluck('key')
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        $interests = collect($this->interests ?? [])
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        if ($interests === []) {
            return [];
        }

        return collect($interests)
            ->unique()
            ->filter(static fn (string $value) => in_array($value, $supported, true))
            ->values()
            ->all();
    }

    public function onboardingCompletedAtIso(): ?string
    {
        $value = $this->onboarding_completed_at;

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

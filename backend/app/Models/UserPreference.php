<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Support\SidebarSectionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserPreference extends Model
{
    public const DEFAULT_BORTLE_CLASS = 6;
    public const MAX_SIDEBAR_WIDGETS_PER_SCOPE = 3;

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
        'sidebar_widget_keys',
    ];

    protected $casts = [
        'event_types' => 'array',
        'interests' => 'array',
        'location_lat' => 'float',
        'location_lon' => 'float',
        'onboarding_completed_at' => 'datetime',
        'bortle_class' => 'integer',
        'sidebar_widget_keys' => 'array',
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

    /**
     * @return list<string>
     */
    public function normalizedSidebarWidgetKeys(string $scope = SidebarSectionRegistry::SCOPE_HOME): array
    {
        $overrides = $this->normalizedSidebarWidgetOverrides();

        return $overrides[$scope] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function normalizedSidebarWidgetOverrides(): array
    {
        $raw = $this->sidebar_widget_keys;
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        if (array_is_list($raw)) {
            $home = self::normalizeSidebarWidgetKeyList($raw);
            if ($home === []) {
                return [];
            }

            return [
                SidebarSectionRegistry::SCOPE_HOME => $home,
            ];
        }

        $validScopes = SidebarSectionRegistry::scopes();
        $result = [];

        foreach ($raw as $scope => $keys) {
            if (!is_string($scope) || !in_array($scope, $validScopes, true)) {
                continue;
            }

            if (!is_array($keys)) {
                continue;
            }

            $result[$scope] = self::normalizeSidebarWidgetKeyList($keys);
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $value
     * @return list<string>
     */
    private static function normalizeSidebarWidgetKeyList(array $value): array
    {
        $supported = collect(SidebarSectionRegistry::sections())
            ->pluck('section_key')
            ->filter(static fn ($item) => is_string($item) && $item !== '')
            ->values()
            ->all();

        $sidebarWidgetKeys = collect($value)
            ->filter(static fn ($item) => is_string($item) && trim($item) !== '')
            ->map(static fn (string $item) => trim($item))
            ->unique()
            ->filter(static fn (string $item) => in_array($item, $supported, true))
            ->take(self::MAX_SIDEBAR_WIDGETS_PER_SCOPE)
            ->values()
            ->all();

        return $sidebarWidgetKeys;
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

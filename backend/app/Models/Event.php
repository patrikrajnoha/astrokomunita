<?php

namespace App\Models;

use App\Enums\RegionScope;
use App\Services\Events\EventInsightsCacheService;
use App\Support\EventFollowTable;
use App\Support\EventTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class Event extends Model
{
    private static ?bool $hasRegionScopeColumn = null;
    private const SIDEBAR_WIDGET_CACHE_KEYS = [
        'widget:upcoming-events:v1',
        'widget:next-eclipse:v1',
        'widget:next-meteor-shower:v1',
    ];

    protected $fillable = [
        'title',
        'type',
        'icon_emoji',
        'region_scope',
        'start_at',
        'end_at',
        'max_at',
        'time_type',
        'time_precision',
        'event_date',
        'short',
        'description',
        'visibility',
        'source_name',
        'source_uid',
        'source_hash',
        'fingerprint_v2',
        'confidence_score',
        'canonical_key',
        'matched_sources',
    ];

    /**
     * Atribúty, ktoré by mali byť pretypované.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'max_at' => 'datetime',
        'event_date' => 'datetime',
        'visibility' => 'integer',
        'confidence_score' => 'decimal:2',
        'matched_sources' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            $defaultType = $event->source_name === 'manual' ? EventTime::TYPE_START : null;
            $defaultPrecision = $event->source_name === 'manual' ? EventTime::PRECISION_EXACT : null;

            $event->time_type = EventTime::normalizeType(
                $event->time_type ?: $defaultType,
                $event->start_at,
                $event->max_at
            );
            $event->time_precision = EventTime::normalizePrecision(
                $event->time_precision ?: $defaultPrecision,
                $event->start_at,
                $event->max_at,
                $event->source_name
            );

            if (self::supportsEventDateColumn()) {
                $event->event_date = $event->resolveEventDate();
            }
        });

        static::updated(function (self $event): void {
            $insightsCache = app(EventInsightsCacheService::class);
            if ($insightsCache->shouldInvalidateForUpdate($event)) {
                $insightsCache->invalidate($event);
            }
        });

        static::saved(function (): void {
            self::flushSidebarWidgetCaches();
        });

        static::deleted(function (self $event): void {
            app(EventInsightsCacheService::class)->invalidate($event);
            self::flushSidebarWidgetCaches();
        });
    }

    /**
     * Scope pre odfiltrovanie len publikovaných udalostí (zo zdroja).
     * Zabezpečí, že seed dáta a pracovné verzie sa nedostanú na frontend.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('source_name')
            ->whereNotNull('source_uid');
    }

    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        $preferences = $user->relationLoaded('eventPreference')
            ? $user->eventPreference
            : $user->eventPreference()->first();

        if (! $preferences) {
            return $query;
        }

        $types = $preferences->normalizedEventTypes();
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $preferredRegion = $preferences->regionEnum();
        if ($preferredRegion !== RegionScope::Global && self::supportsRegionScope()) {
            $query->whereIn('region_scope', RegionScope::visibleFor($preferredRegion));
        }

        return $query;
    }

    public static function supportsRegionScope(): bool
    {
        if (self::$hasRegionScopeColumn !== null) {
            return self::$hasRegionScopeColumn;
        }

        self::$hasRegionScopeColumn = Schema::hasColumn('events', 'region_scope');

        return self::$hasRegionScopeColumn;
    }

    public static function supportsEventDateColumn(): bool
    {
        return Schema::hasColumn('events', 'event_date');
    }

    /**
     * Vzťah k obľúbeným položkám.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, EventFollowTable::resolve())
            ->withPivot(['created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(EventInvite::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }

    private function resolveEventDate(): CarbonInterface|string|null
    {
        return $this->start_at ?? $this->max_at;
    }

    private static function flushSidebarWidgetCaches(): void
    {
        foreach (self::SIDEBAR_WIDGET_CACHE_KEYS as $cacheKey) {
            Cache::forget($cacheKey);
        }
    }
}

<?php

namespace App\Models;

use App\Enums\RegionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Event extends Model
{
    private static ?bool $hasRegionScopeColumn = null;

    protected $fillable = [
        'title',
        'type',
        'region_scope',
        'start_at',
        'end_at',
        'max_at',
        'short',
        'description',
        'visibility',
        'source_name',
        'source_uid',
        'source_hash',
    ];
    /**
     * Atribúty, ktoré by mali byť pretypované.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
        'max_at'     => 'datetime',
        'visibility' => 'integer',
    ];

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
        if (!$user) {
            return $query;
        }

        $preferences = $user->relationLoaded('eventPreference')
            ? $user->eventPreference
            : $user->eventPreference()->first();

        if (!$preferences) {
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

    /**
     * Vzťah k obľúbeným položkám.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}

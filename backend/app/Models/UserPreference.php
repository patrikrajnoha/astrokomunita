<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\RegionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'event_types',
        'region',
    ];

    protected $casts = [
        'event_types' => 'array',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use App\Support\EventTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'event_type',
        'starts_at',
        'ends_at',
        'time_type',
        'time_precision',
        'visibility',
        'created_by',
        'status',
        'published_event_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'visibility' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $manualEvent): void {
            $manualEvent->time_type = EventTime::normalizeType(
                $manualEvent->time_type ?: EventTime::TYPE_START,
                $manualEvent->starts_at,
                $manualEvent->starts_at
            );
            $manualEvent->time_precision = EventTime::normalizePrecision(
                $manualEvent->time_precision ?: EventTime::PRECISION_EXACT,
                $manualEvent->starts_at,
                $manualEvent->starts_at
            );
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'published_event_id');
    }
}

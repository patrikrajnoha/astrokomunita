<?php

namespace App\Models;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'published_event_id');
    }
}

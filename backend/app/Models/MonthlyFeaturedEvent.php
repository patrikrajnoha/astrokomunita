<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyFeaturedEvent extends Model
{
    protected $fillable = [
        'event_id',
        'position',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_active' => 'boolean',
        'event_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


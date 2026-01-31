<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEmailAlert extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'email',
        'created_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

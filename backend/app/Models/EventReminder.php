<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReminder extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'minutes_before',
        'remind_at',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'event_id' => 'integer',
        'minutes_before' => 'integer',
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

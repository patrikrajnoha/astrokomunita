<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'source_id',
        'enabled',
        'interval_minutes',
        'jitter_seconds',
        'timezone',
        'last_run_at',
        'next_run_at',
        'last_result',
        'last_message',
    ];

    protected $casts = [
        'bot_user_id' => 'integer',
        'source_id' => 'integer',
        'enabled' => 'boolean',
        'interval_minutes' => 'integer',
        'jitter_seconds' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bot_user_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BotSource::class, 'source_id');
    }
}


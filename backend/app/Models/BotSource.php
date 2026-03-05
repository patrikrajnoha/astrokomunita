<?php

namespace App\Models;

use App\Enums\BotSourceType;
use App\Enums\PostBotIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'bot_identity',
        'source_type',
        'url',
        'is_enabled',
        'schedule',
        'last_run_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'consecutive_failures',
        'last_status_code',
        'avg_latency_ms',
        'cooldown_until',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'source_type' => BotSourceType::class,
        'is_enabled' => 'boolean',
        'schedule' => 'array',
        'last_run_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'last_status_code' => 'integer',
        'avg_latency_ms' => 'integer',
        'cooldown_until' => 'datetime',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(BotRun::class, 'source_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BotItem::class, 'source_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BotSchedule::class, 'source_id');
    }
}

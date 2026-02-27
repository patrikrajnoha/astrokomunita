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
        'bot_identity',
        'source_type',
        'url',
        'is_enabled',
        'schedule',
        'last_run_at',
        'cooldown_until',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'source_type' => BotSourceType::class,
        'is_enabled' => 'boolean',
        'schedule' => 'array',
        'last_run_at' => 'datetime',
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
}

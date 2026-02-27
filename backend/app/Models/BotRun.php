<?php

namespace App\Models;

use App\Enums\BotRunStatus;
use App\Enums\PostBotIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_identity',
        'source_id',
        'started_at',
        'finished_at',
        'status',
        'stats',
        'meta',
        'error_text',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'status' => BotRunStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'stats' => 'array',
        'meta' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(BotSource::class, 'source_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BotItem::class, 'run_id');
    }
}

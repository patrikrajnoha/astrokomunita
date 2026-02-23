<?php

namespace App\Models;

use App\Enums\BotRunStatus;
use App\Enums\PostBotIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'error_text',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'status' => BotRunStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'stats' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(BotSource::class, 'source_id');
    }
}


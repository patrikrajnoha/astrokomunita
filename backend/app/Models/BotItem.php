<?php

namespace App\Models;

use App\Enums\BotPublishStatus;
use App\Enums\BotTranslationStatus;
use App\Enums\PostBotIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_identity',
        'source_id',
        'run_id',
        'post_id',
        'stable_key',
        'title',
        'summary',
        'content',
        'url',
        'published_at',
        'fetched_at',
        'lang_original',
        'lang_detected',
        'title_translated',
        'content_translated',
        'translation_status',
        'translation_error',
        'translation_provider',
        'translated_at',
        'publish_status',
        'meta',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
        'translation_status' => BotTranslationStatus::class,
        'translated_at' => 'datetime',
        'publish_status' => BotPublishStatus::class,
        'meta' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(BotSource::class, 'source_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BotRun::class, 'run_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}

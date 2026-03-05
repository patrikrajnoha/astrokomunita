<?php

namespace App\Models;

use App\Enums\PostBotIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_identity',
        'source_id',
        'run_id',
        'bot_item_id',
        'post_id',
        'actor_user_id',
        'action',
        'outcome',
        'reason',
        'run_context',
        'message',
        'meta',
    ];

    protected $casts = [
        'bot_identity' => PostBotIdentity::class,
        'source_id' => 'integer',
        'run_id' => 'integer',
        'bot_item_id' => 'integer',
        'post_id' => 'integer',
        'actor_user_id' => 'integer',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(BotItem::class, 'bot_item_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}


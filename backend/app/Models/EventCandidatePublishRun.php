<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCandidatePublishRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_FAILURES = 'completed_with_failures';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'reviewer_user_id',
        'publish_generation_mode',
        'total_selected',
        'processed',
        'published',
        'failed',
        'limit_applied',
        'started_at',
        'finished_at',
        'filters',
        'meta',
        'error_message',
    ];

    protected $casts = [
        'reviewer_user_id' => 'integer',
        'total_selected' => 'integer',
        'processed' => 'integer',
        'published' => 'integer',
        'failed' => 'integer',
        'limit_applied' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'filters' => 'array',
        'meta' => 'array',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_FAILURES,
            self::STATUS_FAILED,
        ], true);
    }
}

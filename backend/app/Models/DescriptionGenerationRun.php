<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescriptionGenerationRun extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'requested_mode',
        'effective_mode',
        'fallback_mode',
        'resume_enabled',
        'force_enabled',
        'dry_run',
        'from_id',
        'limit',
        'last_event_id',
        'processed',
        'generated',
        'failed',
        'skipped',
        'meta',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'resume_enabled' => 'boolean',
        'force_enabled' => 'boolean',
        'dry_run' => 'boolean',
        'from_id' => 'integer',
        'limit' => 'integer',
        'last_event_id' => 'integer',
        'processed' => 'integer',
        'generated' => 'integer',
        'failed' => 'integer',
        'skipped' => 'integer',
        'meta' => 'array',
    ];
}


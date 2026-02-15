<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlRun extends Model
{
    protected $fillable = [
        'event_source_id',
        'source_name',
        'source_url',
        'year',
        'started_at',
        'finished_at',
        'status',
        'fetched_bytes',
        'fetched_count',
        'parsed_items',
        'inserted_candidates',
        'created_candidates_count',
        'duplicates',
        'skipped_duplicates_count',
        'errors_count',
        'error_log',
        'error_summary',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'year' => 'integer',
        'fetched_count' => 'integer',
        'created_candidates_count' => 'integer',
        'skipped_duplicates_count' => 'integer',
    ];

    public function eventSource(): BelongsTo
    {
        return $this->belongsTo(EventSource::class);
    }
}

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
        'source_year',
        'year',
        'started_at',
        'finished_at',
        'duration_ms',
        'status',
        'headers_used',
        'fetched_bytes',
        'fetched_count',
        'parsed_items',
        'inserted_candidates',
        'created_candidates_count',
        'updated_candidates_count',
        'duplicates',
        'skipped_duplicates_count',
        'errors_count',
        'diagnostics',
        'error_code',
        'error_log',
        'error_summary',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'source_year' => 'integer',
        'year' => 'integer',
        'duration_ms' => 'integer',
        'headers_used' => 'boolean',
        'fetched_count' => 'integer',
        'created_candidates_count' => 'integer',
        'updated_candidates_count' => 'integer',
        'skipped_duplicates_count' => 'integer',
    ];

    public function eventSource(): BelongsTo
    {
        return $this->belongsTo(EventSource::class);
    }
}

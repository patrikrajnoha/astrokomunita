<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlRun extends Model
{
    protected $fillable = [
        'source_name',
        'source_url',
        'started_at',
        'finished_at',
        'fetched_bytes',
        'parsed_items',
        'inserted_candidates',
        'duplicates',
        'errors_count',
        'error_log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}

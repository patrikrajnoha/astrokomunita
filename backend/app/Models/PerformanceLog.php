<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceLog extends Model
{
    protected $fillable = [
        'key',
        'environment',
        'sample_size',
        'duration_ms',
        'avg_ms',
        'p95_ms',
        'min_ms',
        'max_ms',
        'db_queries_avg',
        'db_queries_p95',
        'payload',
        'created_by',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'sample_size' => 'integer',
        'duration_ms' => 'integer',
        'avg_ms' => 'decimal:2',
        'p95_ms' => 'decimal:2',
        'min_ms' => 'integer',
        'max_ms' => 'integer',
        'db_queries_avg' => 'decimal:2',
        'db_queries_p95' => 'decimal:2',
        'payload' => 'array',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


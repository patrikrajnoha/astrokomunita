<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'decision',
        'scores',
        'labels',
        'model_versions',
        'latency_ms',
        'error_code',
        'request_hash',
        'request_excerpt',
        'reviewed_by_admin_id',
        'admin_action',
        'admin_note',
    ];

    protected $casts = [
        'scores' => 'array',
        'labels' => 'array',
        'model_versions' => 'array',
        'latency_ms' => 'integer',
        'entity_id' => 'integer',
        'reviewed_by_admin_id' => 'integer',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}

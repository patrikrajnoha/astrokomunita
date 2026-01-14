<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCandidate extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_DUPLICATE = 'duplicate';

    protected $fillable = [
        'source_name',
        'source_url',
        'source_uid',
        'source_hash',

        'title',
        'type',
        'max_at',
        'start_at',
        'end_at',

        'short',
        'description',
        'visibility',

        'raw_payload',

        'status',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
    ];

    protected $casts = [
        'max_at'      => 'datetime',
        'start_at'    => 'datetime',
        'end_at'      => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}

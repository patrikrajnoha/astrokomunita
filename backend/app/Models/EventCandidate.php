<?php

namespace App\Models;

use App\Enums\EventCandidateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCandidate extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = EventCandidateStatus::Pending->value;
    public const STATUS_APPROVED  = EventCandidateStatus::Approved->value;
    public const STATUS_REJECTED  = EventCandidateStatus::Rejected->value;
    public const STATUS_DUPLICATE = EventCandidateStatus::Duplicate->value;
    public const TRANSLATION_PENDING = 'pending';
    public const TRANSLATION_DONE = 'done';
    public const TRANSLATION_FAILED = 'failed';

    protected $fillable = [
        'event_source_id',
        'source_name',
        'source_url',
        'source_uid',
        'external_id',
        'stable_key',
        'source_hash',

        'title',
        'original_title',
        'translated_title',
        'raw_type',
        'type',
        'max_at',
        'start_at',
        'end_at',

        'short',
        'description',
        'original_description',
        'translated_description',
        'visibility',
        'translation_status',
        'translation_error',
        'translated_at',

        'raw_payload',

        'status',
        'reviewed_by',
        'reviewed_at',
        'published_event_id',
        'reject_reason',
    ];

    protected $casts = [
        'max_at'      => 'datetime',
        'start_at'    => 'datetime',
        'end_at'      => 'datetime',
        'translated_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function eventSource(): BelongsTo
    {
        return $this->belongsTo(EventSource::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}

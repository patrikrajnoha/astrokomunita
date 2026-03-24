<?php

namespace App\Models;

use App\Enums\EventCandidateStatus;
use App\Support\EventTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class EventCandidate extends Model
{
    use HasFactory;

    private static ?bool $hasTimeColumns = null;

    public const STATUS_PENDING = EventCandidateStatus::Pending->value;

    public const STATUS_APPROVED = EventCandidateStatus::Approved->value;

    public const STATUS_REJECTED = EventCandidateStatus::Rejected->value;

    public const STATUS_DUPLICATE = EventCandidateStatus::Duplicate->value;

    public const TRANSLATION_PENDING = 'pending';

    public const TRANSLATION_DONE = 'done';

    public const TRANSLATION_FAILED = 'failed';

    public const TRANSLATION_MODE_TEMPLATE = 'template';

    public const TRANSLATION_MODE_TRANSLATED = 'translated';

    public const TRANSLATION_MODE_AI_TITLE = 'ai_title';

    public const TRANSLATION_MODE_AI_DESCRIPTION = 'ai_description';

    public const TRANSLATION_MODE_AI_REFINED = 'ai_refined';

    public const TRANSLATION_MODE_MANUAL = 'manual';

    protected $fillable = [
        'event_source_id',
        'source_name',
        'source_url',
        'source_uid',
        'external_id',
        'stable_key',
        'confidence_score',
        'canonical_key',
        'matched_sources',
        'source_hash',
        'fingerprint_v2',

        'title',
        'original_title',
        'translated_title',
        'raw_type',
        'type',
        'max_at',
        'start_at',
        'end_at',
        'time_type',
        'time_precision',

        'short',
        'description',
        'original_description',
        'translated_description',
        'visibility',
        'translation_status',
        'translation_mode',
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
        'max_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'translated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'confidence_score' => 'decimal:2',
        'matched_sources' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $candidate): void {
            if (! self::supportsTimeColumns()) {
                return;
            }

            $candidate->time_type = EventTime::normalizeType(
                $candidate->time_type,
                $candidate->start_at,
                $candidate->max_at
            );
            $candidate->time_precision = EventTime::normalizePrecision(
                $candidate->time_precision,
                $candidate->start_at,
                $candidate->max_at,
                $candidate->source_name
            );
        });
    }

    public static function supportsTimeColumns(): bool
    {
        if (self::$hasTimeColumns !== null) {
            return self::$hasTimeColumns;
        }

        self::$hasTimeColumns = Schema::hasColumns('event_candidates', ['time_type', 'time_precision']);

        return self::$hasTimeColumns;
    }

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

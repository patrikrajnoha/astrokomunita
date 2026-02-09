<?php

namespace App\Models;

use App\Enums\RssItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = RssItemStatus::Pending->value;
    public const STATUS_APPROVED = RssItemStatus::Approved->value;
    public const STATUS_SCHEDULED = RssItemStatus::Scheduled->value;
    public const STATUS_PUBLISHED = RssItemStatus::Published->value;
    public const STATUS_DISCARDED = RssItemStatus::Discarded->value;
    public const STATUS_ERROR = RssItemStatus::Error->value;

    protected $fillable = [
        'source',
        'guid',
        'url',
        'dedupe_hash',
        'title',
        'summary',
        'published_at',
        'fetched_at',
        'status',
        'scheduled_for',
        'post_id',
        'last_error',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope: items fetched today (server timezone)
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('fetched_at', now()->toDateString());
    }

    /**
     * Scope: filter by status
     */
    public function scopeByStatus(Builder $query, string|RssItemStatus|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];
        $normalized = array_map(
            fn ($item) => $item instanceof RssItemStatus ? $item->value : (string) $item,
            $statuses
        );
        return $query->whereIn('status', $normalized);
    }

    /**
     * Scope: filter by source
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope: pending items (newly fetched)
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: scheduled items ready to publish (scheduled_for <= now)
     */
    public function scopeScheduledReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_for', '<=', now());
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Is this item fetched today?
     */
    public function isToday(): bool
    {
        return $this->fetched_at && $this->fetched_at->isToday();
    }

    /**
     * Can be published (pending or approved)
     */
    public function canPublish(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Is already published?
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->post_id;
    }
}

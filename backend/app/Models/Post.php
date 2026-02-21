<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Hashtag;
use App\Services\Storage\MediaStorageService;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'root_id',
        'depth',
        'content',
        'original_title',
        'original_body',
        'translated_title',
        'translated_body',
        'translation_status',
        'translation_error',
        'translated_at',
        'views',
        'source_name',
        'source_url',
        'source_uid',
        'source_published_at',
        'expires_at',  // When AstroBot posts should expire
        'is_hidden',
        'moderation_status',
        'moderation_summary',
        'hidden_reason',
        'hidden_at',
        'attachment_path',
        'attachment_original_path',
        'attachment_web_path',
        'attachment_mime',
        'attachment_original_mime',
        'attachment_web_mime',
        'attachment_original_name',
        'attachment_size',
        'attachment_original_size',
        'attachment_web_size',
        'attachment_web_width',
        'attachment_web_height',
        'attachment_variants_json',
        'attachment_moderation_status',
        'attachment_moderation_summary',
        'attachment_is_blurred',
        'attachment_hidden_at',
        'pinned_at',
    ];

    protected $appends = [
        'attachment_url',
        'attachment_download_url',
        'attachment_width',
        'attachment_height',
        'attachment_size_web',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'root_id' => 'integer',
        'depth' => 'integer',
        'views' => 'integer',
        'attachment_size' => 'integer',
        'attachment_original_size' => 'integer',
        'attachment_web_size' => 'integer',
        'attachment_web_width' => 'integer',
        'attachment_web_height' => 'integer',
        'attachment_variants_json' => 'array',
        'source_published_at' => 'datetime',
        'translated_at' => 'datetime',
        'expires_at' => 'datetime',
        'pinned_at' => 'datetime',
        'is_hidden' => 'boolean',
        'moderation_summary' => 'array',
        'hidden_at' => 'datetime',
        'attachment_moderation_summary' => 'array',
        'attachment_is_blurred' => 'boolean',
        'attachment_hidden_at' => 'datetime',
        'liked_by_me' => 'boolean',
        'is_bookmarked' => 'boolean',
        'bookmarked_at' => 'datetime',
    ];

    /**
     * Autor postu.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Likes na tento post.
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes');
    }

    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_user_bookmarks')
            ->withPivot('created_at');
    }

    /**
     * Parent post (ak ide o reply).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Replies na tento post (1 úroveň – MVP).
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest();
    }

    /**
     * Tags on this post.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    /**
     * Hashtags on this post.
     */
    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'hashtag_post');
    }

    /**
     * Root post (ak ide o reply).
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    /**
     * Verejná URL pre attachment (absolútna).
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        $path = $this->attachment_web_path ?: $this->attachment_path;
        if (!$path) {
            return null;
        }

        return app(MediaStorageService::class)->absoluteUrl($path);
    }

    public function getAttachmentDownloadUrlAttribute(): ?string
    {
        if (!$this->isImageAttachment()) {
            return null;
        }

        if (!$this->attachment_original_path && !$this->attachment_path) {
            return null;
        }

        return route('media.download', ['media' => $this->id], false);
    }

    public function getAttachmentWidthAttribute(): ?int
    {
        if ($this->attachment_web_width !== null) {
            return (int) $this->attachment_web_width;
        }

        return null;
    }

    public function getAttachmentHeightAttribute(): ?int
    {
        if ($this->attachment_web_height !== null) {
            return (int) $this->attachment_web_height;
        }

        return null;
    }

    public function getAttachmentSizeWebAttribute(): ?int
    {
        if ($this->attachment_web_size !== null) {
            return (int) $this->attachment_web_size;
        }

        if ($this->attachment_size !== null && $this->isImageAttachment()) {
            return (int) $this->attachment_size;
        }

        return null;
    }

    /**
     * Check if this post is from a bot user.
     */
    public function isFromBot(): bool
    {
        return $this->user?->isBot() ?? false;
    }

    /**
     * Check if this post has expired (for AstroBot posts).
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope to exclude expired AstroBot posts from queries.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopePubliclyVisible($query)
    {
        return $query
            ->where('is_hidden', false)
            ->whereNull('hidden_at')
            ->where('moderation_status', '!=', 'blocked');
    }

    /**
     * Scope to get only expired posts for cleanup.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
    }

    /**
     * Polia viditeľné v JSON (API response).
     * replies_count sa objaví pri withCount('replies').
     */
    protected $visible = [
        'id',
        'user_id',
        'user',        // ✅ bez toho sa relation user do JSON neobjaví
        'parent',
        'parent_id',
        'replies',
        'root',
        'root_id',
        'depth',
        'content',
        'views',
        'tags',        // Hashtag tags
        'hashtags',    // New hashtags
        'source_name',
        'source_url',
        'source_uid',
        'source_published_at',
        'is_hidden',
        'moderation_status',
        'moderation_summary',
        'hidden_reason',
        'hidden_at',
        'attachment_path',
        'attachment_original_path',
        'attachment_web_path',
        'attachment_mime',
        'attachment_original_mime',
        'attachment_web_mime',
        'attachment_original_name',
        'attachment_size',
        'attachment_original_size',
        'attachment_web_size',
        'attachment_web_width',
        'attachment_web_height',
        'attachment_variants_json',
        'attachment_moderation_status',
        'attachment_moderation_summary',
        'attachment_is_blurred',
        'attachment_hidden_at',
        'attachment_url',
        'attachment_download_url',
        'attachment_width',
        'attachment_height',
        'attachment_size_web',
        'pinned_at',
        'expires_at',
        'created_at',
        'updated_at',
        'replies_count',
        'likes_count',
        'liked_by_me',
        'is_bookmarked',
        'bookmarked_at',
    ];

    private function isImageAttachment(): bool
    {
        $mime = strtolower(trim((string) ($this->attachment_original_mime ?: $this->attachment_mime)));
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $name = strtolower((string) ($this->attachment_original_name ?: ''));
        return str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg')
            || str_ends_with($name, '.png')
            || str_ends_with($name, '.gif')
            || str_ends_with($name, '.webp');
    }
}




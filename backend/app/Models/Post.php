<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\Hashtag;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'root_id',
        'depth',
        'content',
        'views',
        'source_name',
        'source_url',
        'source_uid',
        'source_published_at',
        'expires_at',  // When AstroBot posts should expire
        'is_hidden',
        'hidden_reason',
        'attachment_path',
        'attachment_mime',
        'attachment_original_name',
        'attachment_size',
        'pinned_at',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'root_id' => 'integer',
        'depth' => 'integer',
        'views' => 'integer',
        'attachment_size' => 'integer',
        'source_published_at' => 'datetime',
        'expires_at' => 'datetime',
        'pinned_at' => 'datetime',
        'is_hidden' => 'boolean',
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

    /**
     * Verejná URL pre attachment (absolútna).
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->attachment_path);

        // Ak už je absolútna, necháme ju
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Inak doplníme APP_URL
        return rtrim(config('app.url'), '/') . $url;
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
        'hidden_reason',
        'attachment_path',
        'attachment_mime',
        'attachment_original_name',
        'attachment_size',
        'attachment_url',
        'pinned_at',
        'expires_at',
        'created_at',
        'updated_at',
        'replies_count',
        'likes_count',
        'liked_by_me',
    ];
}

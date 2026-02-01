<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'root_id',
        'depth',
        'content',
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
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'root_id' => 'integer',
        'depth' => 'integer',
        'attachment_size' => 'integer',
        'source_published_at' => 'datetime',
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
        'created_at',
        'updated_at',
        'replies_count',
        'likes_count',
        'liked_by_me',
    ];
}

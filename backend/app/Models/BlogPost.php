<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'published_at',
        'cover_image_path',
        'cover_image_mime',
        'cover_image_original_name',
        'cover_image_size',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'published_at' => 'datetime',
        'cover_image_size' => 'integer',
        'views' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogPostComment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->cover_image_path);

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim(config('app.url'), '/') . $url;
    }
}

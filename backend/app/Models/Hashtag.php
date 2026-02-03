<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hashtag extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Posts s týmto hashtagom.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'hashtag_post');
    }

    /**
     * Získa počet postov za posledných 24 hodín.
     */
    public function getPostsCountLast24HoursAttribute(): int
    {
        return $this->posts()
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
    }
}

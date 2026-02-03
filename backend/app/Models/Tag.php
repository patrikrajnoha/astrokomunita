<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
    ];

    /**
     * Posts that have this tag.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags');
    }

    /**
     * Blog posts that have this tag.
     */
    public function blogPosts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    /**
     * Boot the model and handle slug generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            $tag->generateSlug();
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name')) {
                $tag->generateSlug();
            }
        });
    }

    /**
     * Generate a unique slug from the name.
     */
    protected function generateSlug()
    {
        // Ensure name is cast to string before generating slug
        $slug = Str::slug((string) $this->name);
        
        // Ensure uniqueness by adding suffix if needed
        $originalSlug = $slug;
        $counter = 1;
        
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        $this->slug = $slug;
    }
}

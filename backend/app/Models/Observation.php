<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Observation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'feed_post_id',
        'title',
        'description',
        'observed_at',
        'location_lat',
        'location_lng',
        'location_name',
        'visibility_rating',
        'equipment',
        'is_public',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'event_id' => 'integer',
        'feed_post_id' => 'integer',
        'observed_at' => 'datetime',
        'location_lat' => 'float',
        'location_lng' => 'float',
        'visibility_rating' => 'integer',
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ObservationMedia::class)->orderBy('id');
    }

    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'feed_post_id');
    }
}

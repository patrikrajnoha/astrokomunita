<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'ends_at',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'ends_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function options(): HasMany
    {
        return $this->pollOptions();
    }

    public function pollOptions(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->pollVotes();
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}

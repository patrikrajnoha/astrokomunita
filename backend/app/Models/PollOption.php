<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'text',
        'image_path',
        'position',
        'votes_count',
    ];

    protected $casts = [
        'poll_id' => 'integer',
        'position' => 'integer',
        'votes_count' => 'integer',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
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


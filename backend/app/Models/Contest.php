<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'hashtag',
        'starts_at',
        'ends_at',
        'winner_post_id',
        'winner_user_id',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        return $this->starts_at && $this->ends_at
            && $now->greaterThanOrEqualTo($this->starts_at)
            && $now->lessThanOrEqualTo($this->ends_at);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function winnerPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'winner_post_id');
    }

    public function winnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}

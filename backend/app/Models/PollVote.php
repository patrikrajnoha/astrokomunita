<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'poll_id',
        'poll_option_id',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'poll_id' => 'integer',
        'poll_option_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function pollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function option(): BelongsTo
    {
        return $this->pollOption();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

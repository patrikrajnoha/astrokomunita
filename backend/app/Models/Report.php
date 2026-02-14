<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_user_id',
        'target_id',
        'reason',
        'message',
        'status',
        'admin_action',
        'reviewed_by',
    ];

    protected $attributes = [
        'target_type' => 'post',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'target_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

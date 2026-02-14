<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AstroBotRun extends Model
{
    use HasFactory;

    protected $table = 'astrobot_runs';

    protected $fillable = [
        'source',
        'trigger',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'new_items',
        'published_items',
        'deleted_items',
        'errors',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];
}

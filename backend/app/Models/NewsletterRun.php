<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'week_start_date',
        'status',
        'total_recipients',
        'sent_count',
        'preview_count',
        'unsubscribe_count',
        'failed_count',
        'started_at',
        'finished_at',
        'admin_user_id',
        'forced',
        'dry_run',
        'error',
        'meta',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'preview_count' => 'integer',
        'unsubscribe_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'admin_user_id' => 'integer',
        'forced' => 'boolean',
        'dry_run' => 'boolean',
        'meta' => 'array',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function featuredEvents(): HasMany
    {
        return $this->hasMany(NewsletterFeaturedEvent::class);
    }
}

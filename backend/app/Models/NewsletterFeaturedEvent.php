<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterFeaturedEvent extends Model
{
    protected $fillable = [
        'newsletter_run_id',
        'event_id',
        'order',
    ];

    protected $casts = [
        'newsletter_run_id' => 'integer',
        'event_id' => 'integer',
        'order' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NewsletterRun::class, 'newsletter_run_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

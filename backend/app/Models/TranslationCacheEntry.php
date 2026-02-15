<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationCacheEntry extends Model
{
    protected $fillable = [
        'cache_key',
        'original_text_hash',
        'language_from',
        'language_to',
        'provider',
        'translated_text',
        'hit_count',
        'last_used_at',
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'last_used_at' => 'datetime',
    ];
}

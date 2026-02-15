<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationLog extends Model
{
    protected $fillable = [
        'provider',
        'status',
        'error_code',
        'duration_ms',
        'language_from',
        'language_to',
        'original_text_hash',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
    ];
}

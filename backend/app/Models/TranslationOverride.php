<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationOverride extends Model
{
    protected $fillable = [
        'source_term',
        'target_term',
        'language_from',
        'language_to',
        'is_case_sensitive',
    ];

    protected $casts = [
        'is_case_sensitive' => 'boolean',
    ];
}

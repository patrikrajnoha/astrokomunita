<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'current_email',
        'new_email',
        'current_email_confirmed_at',
        'new_email_applied_at',
        'expires_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_email_confirmed_at' => 'datetime',
            'new_email_applied_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }
}

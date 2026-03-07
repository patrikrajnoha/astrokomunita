<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    public const PURPOSE_ACCOUNT_VERIFICATION = 'account_verification';
    public const PURPOSE_EMAIL_CHANGE_CURRENT = 'email_change_current';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'user_id',
        'email_change_request_id',
        'email',
        'purpose',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'last_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailChangeRequest(): BelongsTo
    {
        return $this->belongsTo(EmailChangeRequest::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDataExportJob extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'status',
        'request_ip_hash',
        'file_path',
        'file_name',
        'size_bytes',
        'checksum_sha256',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'size_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }
}

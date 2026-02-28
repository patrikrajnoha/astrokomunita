<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'iss_alerts',
        'good_conditions_alerts',
    ];

    protected $casts = [
        'iss_alerts' => 'boolean',
        'good_conditions_alerts' => 'boolean',
    ];

    /**
     * @return array{iss_alerts:bool,good_conditions_alerts:bool}
     */
    public static function defaults(): array
    {
        return [
            'iss_alerts' => false,
            'good_conditions_alerts' => false,
        ];
    }

    public static function ensureForUser(int $userId): self
    {
        $defaults = self::defaults();

        return self::query()->firstOrCreate(
            ['user_id' => $userId],
            $defaults
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

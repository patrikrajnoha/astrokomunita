<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const TYPE_KEYS = [
        'post_like',
        'post_comment',
        'reply',
        'event_reminder',
        'system',
    ];

    protected $fillable = [
        'user_id',
        'in_app_json',
        'email_enabled',
        'email_json',
    ];

    protected $casts = [
        'in_app_json' => 'array',
        'email_enabled' => 'boolean',
        'email_json' => 'array',
    ];

    public static function defaults(): array
    {
        return [
            'in_app' => collect(self::TYPE_KEYS)->mapWithKeys(
                static fn (string $key) => [$key => true]
            )->all(),
            'email_enabled' => false,
        ];
    }

    public static function ensureForUser(int $userId): self
    {
        $defaults = self::defaults();

        return self::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'in_app_json' => $defaults['in_app'],
                'email_enabled' => $defaults['email_enabled'],
                'email_json' => null,
            ]
        );
    }

    public function inApp(): array
    {
        return $this->normalizeMap($this->in_app_json, true);
    }

    public function email(): array
    {
        return $this->normalizeMap($this->email_json, false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private function normalizeMap(mixed $value, bool $default): array
    {
        $raw = is_array($value) ? $value : [];
        $normalized = [];

        foreach (self::TYPE_KEYS as $key) {
            $normalized[$key] = array_key_exists($key, $raw)
                ? (bool) $raw[$key]
                : $default;
        }

        return $normalized;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const EVENT_REMINDER_TYPE_KEYS = [
        'event_reminder_meteors',
        'event_reminder_eclipses',
        'event_reminder_planetary',
        'event_reminder_small_bodies',
        'event_reminder_aurora',
        'event_reminder_space',
        'event_reminder_observing',
    ];

    public const EVENT_REMINDER_TYPE_MAP = [
        'meteors' => 'event_reminder_meteors',
        'meteor_shower' => 'event_reminder_meteors',
        'eclipse' => 'event_reminder_eclipses',
        'eclipse_lunar' => 'event_reminder_eclipses',
        'eclipse_solar' => 'event_reminder_eclipses',
        'conjunction' => 'event_reminder_planetary',
        'planetary_event' => 'event_reminder_planetary',
        'planet' => 'event_reminder_planetary',
        'comet' => 'event_reminder_small_bodies',
        'asteroid' => 'event_reminder_small_bodies',
        'aurora' => 'event_reminder_aurora',
        'space_event' => 'event_reminder_space',
        'mission' => 'event_reminder_space',
        'observation_window' => 'event_reminder_observing',
    ];

    public const TYPE_KEYS = [
        'post_like',
        'post_comment',
        'reply',
        'event_reminder',
        ...self::EVENT_REMINDER_TYPE_KEYS,
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

    public static function eventReminderTypeKey(?string $eventType): ?string
    {
        $normalized = strtolower(trim((string) $eventType));
        if ($normalized === '') {
            return null;
        }

        return self::EVENT_REMINDER_TYPE_MAP[$normalized] ?? null;
    }

    /**
     * @param  array<string, bool>  $map
     */
    public static function allowsEventReminderInAppForType(array $map, ?string $eventType): bool
    {
        if (! (bool) ($map['event_reminder'] ?? true)) {
            return false;
        }

        $typeKey = self::eventReminderTypeKey($eventType);

        return $typeKey === null
            ? true
            : (bool) ($map[$typeKey] ?? true);
    }

    /**
     * @param  array<string, bool>  $map
     */
    public static function allowsEventReminderEmailForType(
        bool $emailEnabled,
        array $map,
        ?string $eventType,
    ): bool {
        if (! $emailEnabled) {
            return false;
        }

        if (! (bool) ($map['event_reminder'] ?? false)) {
            return false;
        }

        $typeKey = self::eventReminderTypeKey($eventType);

        return $typeKey === null
            ? true
            : (bool) ($map[$typeKey] ?? false);
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

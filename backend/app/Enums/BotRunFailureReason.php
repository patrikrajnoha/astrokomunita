<?php

namespace App\Enums;

enum BotRunFailureReason: string
{
    case TRANSLATION_TIMEOUT = 'translation_timeout';
    case PROVIDER_UNAVAILABLE = 'provider_unavailable';
    case STALE_RUN_RECOVERED = 'stale_run_recovered';
    case UNHANDLED_EXCEPTION = 'unhandled_exception';
    case LOCK_CONFLICT = 'lock_conflict';
    case RATE_LIMITED = 'rate_limited';
    case COOLDOWN_RATE_LIMITED = 'cooldown_rate_limited';
    case NEEDS_API_KEY = 'needs_api_key';
    case UNKNOWN = 'unknown';

    public static function fromNullable(mixed $value): self
    {
        $normalized = strtolower(trim((string) $value));

        return self::tryFrom($normalized) ?? self::UNKNOWN;
    }
}

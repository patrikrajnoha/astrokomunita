<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class EventTime
{
    private const UNKNOWN_TIME_FALLBACK_SOURCES = [
        'imo',
    ];

    public const TYPE_START = 'start';
    public const TYPE_PEAK = 'peak';
    public const TYPE_WINDOW = 'window';
    public const TYPE_UNKNOWN = 'unknown';

    public const PRECISION_EXACT = 'exact';
    public const PRECISION_APPROXIMATE = 'approximate';
    public const PRECISION_UNKNOWN = 'unknown';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_START,
            self::TYPE_PEAK,
            self::TYPE_WINDOW,
            self::TYPE_UNKNOWN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function precisions(): array
    {
        return [
            self::PRECISION_EXACT,
            self::PRECISION_APPROXIMATE,
            self::PRECISION_UNKNOWN,
        ];
    }

    public static function normalizeType(mixed $value, mixed $startAt = null, mixed $maxAt = null): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, self::types(), true)) {
            return $normalized;
        }

        $start = self::toUtcCarbon($startAt);
        $max = self::toUtcCarbon($maxAt);

        if (! $start && ! $max) {
            return self::TYPE_UNKNOWN;
        }

        if ($max && (! $start || ! self::sameMoment($start, $max))) {
            return self::TYPE_PEAK;
        }

        return self::TYPE_START;
    }

    public static function normalizePrecision(
        mixed $value,
        mixed $startAt = null,
        mixed $maxAt = null,
        ?string $sourceName = null,
    ): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, self::precisions(), true)) {
            return $normalized;
        }

        if (! self::toUtcCarbon($startAt) && ! self::toUtcCarbon($maxAt)) {
            return self::PRECISION_UNKNOWN;
        }

        if (self::isFallbackMidnight($startAt, $maxAt, $sourceName)) {
            return self::PRECISION_UNKNOWN;
        }

        return self::PRECISION_EXACT;
    }

    public static function isFallbackMidnight(
        mixed $startAt = null,
        mixed $maxAt = null,
        ?string $sourceName = null,
    ): bool
    {
        if (! self::sourceUsesMissingTimeFallback($sourceName)) {
            return false;
        }

        $primary = self::toUtcCarbon($maxAt) ?? self::toUtcCarbon($startAt);
        if (! $primary) {
            return false;
        }

        if ($primary->format('H:i:s') !== '00:00:00') {
            return false;
        }

        $secondary = self::toUtcCarbon($startAt);
        if ($secondary && $secondary->format('H:i:s') !== '00:00:00') {
            return false;
        }

        return true;
    }

    public static function serializeUtc(mixed $value): ?string
    {
        return self::toUtcCarbon($value)?->toAtomString();
    }

    public static function toUtcCarbon(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value)->utc();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function sameMoment(CarbonImmutable $left, CarbonImmutable $right): bool
    {
        return $left->format('Y-m-d H:i:s.u') === $right->format('Y-m-d H:i:s.u');
    }

    private static function sourceUsesMissingTimeFallback(?string $sourceName): bool
    {
        $normalized = strtolower(trim((string) $sourceName));

        return $normalized !== '' && in_array($normalized, self::UNKNOWN_TIME_FALLBACK_SOURCES, true);
    }
}

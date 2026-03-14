<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProfanityFilter
{
    public static function containsBlockedWord(?string $value): bool
    {
        $normalizedValue = self::normalize($value);
        if ($normalizedValue === '') {
            return false;
        }

        foreach (self::blockedWords() as $blockedWord) {
            if ($blockedWord !== '' && str_contains($normalizedValue, $blockedWord)) {
                return true;
            }
        }

        return false;
    }

    private static function blockedWords(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $word): string => self::normalize((string) $word),
            (array) config('auth.username.blocked_words', [])
        )));
    }

    private static function normalize(?string $value): string
    {
        $ascii = Str::lower(Str::ascii(trim((string) $value)));

        if ($ascii === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }
}

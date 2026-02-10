<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

class UsernameRules
{
    private const FORMAT_PATTERN = '/^[a-z][a-z0-9_]{2,19}$/';

    public static function normalize(?string $username): string
    {
        return strtolower(trim((string) $username));
    }

    public static function validationRules(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:20',
            'regex:' . self::FORMAT_PATTERN,
            function (string $attribute, mixed $value, \Closure $fail): void {
                $normalized = self::normalize((string) $value);

                if (str_contains($normalized, '__')) {
                    $fail('Pouzivatelske meno nemoze obsahovat dvojite podciarkovniky.');
                    return;
                }

                if (self::isReserved($normalized)) {
                    $fail('Toto pouzivatelske meno nie je povolene.');
                }
            },
            Rule::unique('users', 'username')->ignore($ignoreUserId),
        ];
    }

    public static function status(?string $rawUsername): array
    {
        $normalized = self::normalize($rawUsername);

        if (!self::isFormatValid($normalized)) {
            return [
                'available' => false,
                'reason' => 'invalid',
                'normalized' => $normalized,
            ];
        }

        if (self::isReserved($normalized)) {
            return [
                'available' => false,
                'reason' => 'reserved',
                'normalized' => $normalized,
            ];
        }

        $taken = User::query()
            ->where('username', $normalized)
            ->exists();

        return [
            'available' => !$taken,
            'reason' => $taken ? 'taken' : 'ok',
            'normalized' => $normalized,
        ];
    }

    public static function isFormatValid(string $normalized): bool
    {
        if (!preg_match(self::FORMAT_PATTERN, $normalized)) {
            return false;
        }

        return !str_contains($normalized, '__');
    }

    public static function isReserved(string $normalized): bool
    {
        $reserved = array_map(
            static fn (string $item): string => self::normalize($item),
            (array) config('auth.username.reserved', [])
        );

        if (in_array($normalized, $reserved, true)) {
            return true;
        }

        foreach ((array) config('auth.username.blocked_words', []) as $blockedWord) {
            $blocked = self::normalize((string) $blockedWord);
            if ($blocked !== '' && str_contains($normalized, $blocked)) {
                return true;
            }
        }

        return false;
    }
}

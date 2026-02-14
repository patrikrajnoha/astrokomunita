<?php

namespace App\Enums;

enum RegionScope: string
{
    case Sk = 'sk';
    case Eu = 'eu';
    case Global = 'global';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $region) => $region->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function visibleFor(self $preferred): array
    {
        return match ($preferred) {
            self::Sk => [self::Sk->value, self::Eu->value, self::Global->value],
            self::Eu => [self::Eu->value, self::Global->value],
            self::Global => [self::Global->value, self::Eu->value, self::Sk->value],
        };
    }
}

<?php

namespace App\Enums;

enum EventSource: string
{
    case ASTROPIXELS = 'astropixels';
    case NASA = 'nasa';

    public function label(): string
    {
        return match ($this) {
            self::ASTROPIXELS => 'AstroPixels Sky Event Almanac',
            self::NASA => 'NASA',
        };
    }
}

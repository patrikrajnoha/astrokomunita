<?php

namespace App\Enums;

enum EventSource: string
{
    case ASTROPIXELS = 'astropixels';
    case GO_ASTRONOMY = 'go_astronomy';
    case NASA = 'nasa';

    public function label(): string
    {
        return match ($this) {
            self::ASTROPIXELS => 'AstroPixels Sky Event Almanac',
            self::GO_ASTRONOMY => 'Go Astronomy Event Calendar',
            self::NASA => 'NASA',
        };
    }
}

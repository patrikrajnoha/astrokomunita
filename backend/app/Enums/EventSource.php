<?php

namespace App\Enums;

enum EventSource: string
{
    case ASTROPIXELS = 'astropixels';
    case NASA = 'nasa';
    case NASA_WATCH_THE_SKIES = 'nasa_watch_the_skies';
    case IMO = 'imo';

    public function label(): string
    {
        return match ($this) {
            self::ASTROPIXELS => 'AstroPixels Sky Event Almanac',
            self::NASA => 'NASA',
            self::NASA_WATCH_THE_SKIES => 'NASA Watch the Skies',
            self::IMO => 'International Meteor Organization',
        };
    }
}

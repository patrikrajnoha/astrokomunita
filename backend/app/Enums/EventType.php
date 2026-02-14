<?php

namespace App\Enums;

enum EventType: string
{
    case Meteors = 'meteors';
    case MeteorShower = 'meteor_shower';
    case Eclipse = 'eclipse';
    case EclipseLunar = 'eclipse_lunar';
    case EclipseSolar = 'eclipse_solar';
    case Conjunction = 'conjunction';
    case PlanetaryEvent = 'planetary_event';
    case Comet = 'comet';
    case Asteroid = 'asteroid';
    case SpaceEvent = 'space_event';
    case ObservationWindow = 'observation_window';
    case Planet = 'planet';
    case Mission = 'mission';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }
}

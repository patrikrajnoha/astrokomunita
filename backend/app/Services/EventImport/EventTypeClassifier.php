<?php

namespace App\Services\EventImport;

class EventTypeClassifier
{
    /**
     * Returns normalized internal event taxonomy.
     */
    public function classify(?string $rawType, ?string $title = null): string
    {
        $raw = $this->norm($rawType);
        $t = $this->norm($title);

        // Eclipse signal can appear in either raw type or title.
        $hasEclipse = $this->hasAny($raw, ['eclipse', 'zatmenie']) || $this->hasAny($t, ['eclipse', 'zatmenie']);

        // 1) Eclipses.
        if (
            $this->hasAny($raw, ['solar_eclipse', 'eclipse_solar', 'solareclipse']) ||
            ($hasEclipse && $this->hasAny($raw.' '.$t, ['solar', 'slnk']))
        ) {
            return 'eclipse_solar';
        }

        if (
            $this->hasAny($raw, ['lunar_eclipse', 'eclipse_lunar', 'lunareclipse']) ||
            ($hasEclipse && $this->hasAny($raw.' '.$t, ['lunar', 'mesiac', 'mes']))
        ) {
            return 'eclipse_lunar';
        }

        // 2) Meteor showers / meteors.
        if (
            $this->hasAny($raw, ['meteor shower', 'meteor_shower', 'shower', 'meteoricky roj', 'meteorický roj', 'roj']) ||
            $this->hasAny($t, ['perseid', 'leonid', 'geminid', 'quadrantid', 'eta aquariid', 'orionid', 'taurid'])
        ) {
            return 'meteor_shower';
        }

        if ($this->hasAny($raw.' '.$t, ['meteor', 'fireball', 'bolid']) && ! $this->hasAny($raw.' '.$t, ['shower', 'roj'])) {
            return 'meteors';
        }

        // 3) Lunar phases.
        // Keep moon phases under observation_window so cross-source canonical keys
        // remain compatible (e.g. AstroPixels vs NASA WTS).
        if (
            $this->hasAny($raw, ['moon_phase', 'moon phase', 'lunar phase']) ||
            $this->hasAny($t, [
                'full moon',
                'new moon',
                'first quarter',
                'last quarter',
                'lunar phase',
                'spln',
                'nov',
                'prva stvrt',
                'prva stvrt mesiaca',
                'prvá štvrť',
                'prvá štvrť mesiaca',
                'posledna stvrt',
                'posledna stvrt mesiaca',
                'posledná štvrť',
                'posledná štvrť mesiaca',
            ])
        ) {
            return 'observation_window';
        }

        // 4) Planetary events.
        if (
            $this->hasAny($raw, ['conjunction', 'konjunkcia', 'opposition', 'opozicia', 'opozícia', 'elongation', 'retrograde', 'stationary']) ||
            $this->hasAny($t, ['konjunkcia', 'opozicia', 'opozícia', 'retrogr', 'elong'])
        ) {
            return 'planetary_event';
        }

        // 5) Comets / asteroids.
        if ($this->hasAny($raw.' '.$t, ['comet', 'kometa', 'komet'])) {
            return 'comet';
        }

        if (
            $this->hasAny($raw, ['asteroid', 'neo', 'nea', 'nasa jpl', 'close approach', 'priblizenie', 'priblíženie']) ||
            $this->hasAny($t, ['asteroid', 'pribl'])
        ) {
            return 'asteroid';
        }

        // 6) ISS / satellites / launches.
        if (
            $this->hasAny($raw, ['iss', 'satellite', 'satelit', 'launch', 'rocket', 'falcon', 'starlink']) ||
            $this->hasAny($t, ['iss', 'starlink', 'launch', 'satelit'])
        ) {
            return 'space_event';
        }

        // 7) Generic observing windows.
        if (
            $this->hasAny($raw, ['best', 'visibility', 'observable', 'window', 'pozorovanie', 'viditelnost', 'viditeľnosť']) ||
            $this->hasAny($t, ['pozor', 'viditeln', 'viditeľn', 'okno'])
        ) {
            return 'observation_window';
        }

        return 'other';
    }

    private function norm(?string $s): string
    {
        $s = trim((string) $s);
        $s = mb_strtolower($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        // Normalize underscore/hyphen variants.
        return str_replace(['-', '__'], ['_', '_'], $s);
    }

    /**
     * @param  array<int,string>  $needles
     */
    private function hasAny(string $haystack, array $needles): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($needles as $n) {
            $n = $this->norm($n);
            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}

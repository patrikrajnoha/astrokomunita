<?php

namespace App\Services\EventImport;

class EventTypeClassifier
{
    /**
     * Jednotná interná taxonómia typov udalostí.
     * Vracia napr.: meteor_shower, meteor, eclipse_solar, eclipse_lunar, planetary_event,
     * comet, asteroid, space_event, observation_window, other
     */
    public function classify(?string $rawType, ?string $title = null): string
    {
        $raw = $this->norm($rawType);
        $t   = $this->norm($title);

        // --- helper: "eclipse" signál môže byť v raw aj v title
        $hasEclipse = $this->hasAny($raw, ['eclipse', 'zatmenie']) || $this->hasAny($t, ['eclipse', 'zatmenie']);

        // 1) ZATMENIA (musí zachytiť aj rawType = "lunar_eclipse"/"solar_eclipse" a aj rawType="eclipse" + title obsahuje solar/lunar)
        if (
            $this->hasAny($raw, ['solar_eclipse', 'eclipse_solar', 'solareclipse']) ||
            ($hasEclipse && $this->hasAny($raw . ' ' . $t, ['solar', 'slnk']))
        ) {
            return 'eclipse_solar';
        }

        if (
            $this->hasAny($raw, ['lunar_eclipse', 'eclipse_lunar', 'lunareclipse']) ||
            ($hasEclipse && $this->hasAny($raw . ' ' . $t, ['lunar', 'mesiac', 'mes']))
        ) {
            return 'eclipse_lunar';
        }

        // 2) METEORICKÉ ROJE / METEORY
        if (
            $this->hasAny($raw, ['meteor shower', 'meteor_shower', 'shower', 'meteorický roj', 'meteoricky roj', 'roj']) ||
            $this->hasAny($t, ['perseid', 'leonid', 'geminid', 'quadrantid', 'eta aquariid', 'orionid', 'taurid'])
        ) {
            return 'meteor_shower';
        }

        if ($this->hasAny($raw . ' ' . $t, ['meteor', 'fireball', 'bolid']) && !$this->hasAny($raw . ' ' . $t, ['shower', 'roj'])) {
            return 'meteor';
        }

        // 3) PLANÉTARNE JAVY
        if (
            $this->hasAny($raw, ['conjunction', 'konjunkcia', 'opposition', 'opozícia', 'opozicia', 'elongation', 'retrograde', 'stationary']) ||
            $this->hasAny($t, ['konjunkcia', 'opozícia', 'opozicia', 'retrogr', 'elong'])
        ) {
            return 'planetary_event';
        }

        // 4) KOMÉTY / ASTEROIDY
        if ($this->hasAny($raw . ' ' . $t, ['comet', 'kométa', 'kometa', 'komét', 'komet'])) {
            return 'comet';
        }

        if (
            $this->hasAny($raw, ['asteroid', 'neo', 'nea', 'nasa jpl', 'close approach', 'priblíženie', 'priblizenie']) ||
            $this->hasAny($t, ['asteroid', 'pribl'])
        ) {
            return 'asteroid';
        }

        // 5) ISS / SATELITY / LAUNCH
        if (
            $this->hasAny($raw, ['iss', 'satellite', 'satelit', 'launch', 'rocket', 'falcon', 'starlink']) ||
            $this->hasAny($t, ['iss', 'starlink', 'launch', 'satelit'])
        ) {
            return 'space_event';
        }

        // 6) POZOROVANIE (okná, viditeľnosť)
        if (
            $this->hasAny($raw, ['best', 'visibility', 'observable', 'window', 'pozorovanie', 'viditeľnosť', 'viditelnost']) ||
            $this->hasAny($t, ['pozor', 'viditeľn', 'viditeln', 'okno'])
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

        // zjednotíme underscore/hyphen a odstránime dvojité medzery
        $s = str_replace(['-', '__'], ['_', '_'], $s);
        return $s;
    }

    private function hasAny(string $haystack, array $needles): bool
    {
        if ($haystack === '') return false;

        foreach ($needles as $n) {
            $n = $this->norm($n);
            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Services\EventImport\Parsers;

use App\Services\EventImport\EventCandidateData;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HtmlTableEventParser implements EventSourceParser
{
    public function parse(string $payload): array
    {
        // 1) Skús najprv existujúcu "table" logiku (ak ju máš)
        // Ak už máš pôvodnú implementáciu, nechaj ju a len pridaj fallback:
        $items = $this->parsePreBlocksAstroPixels($payload);

        return $items;
    }

    /**
     * AstroPixels "Sky Event Almanac" má udalosti v <pre> blokoch, nie v <tr>.
     */
    private function parsePreBlocksAstroPixels(string $payload): array
    {
        $year = $this->extractYear($payload) ?? (int) date('Y');
        $tz   = 'Europe/Bratislava'; // CET/CEST (prakticky pre SK)

        // Vyberieme všetky <pre> bloky (v tej stránke sú 2 hlavné: Jan-Jun a Jul-Dec + ďalšie tabuľky)
        if (!preg_match_all('~<pre>(.*?)</pre>~si', $payload, $m)) {
            return [];
        }

        $items = [];

        foreach ($m[1] as $preHtml) {
            $text = html_entity_decode($preHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Normalizuj nové riadky
            $lines = preg_split("~\r\n|\n|\r~", $text);

            $currentMonth = null;

            foreach ($lines as $lineRaw) {
                $line = rtrim($lineRaw);

                // skip prázdne / hlavičky
                if (trim($line) === '' || str_starts_with(trim($line), 'Date')) {
                    continue;
                }

                // Príklady:
                // "Jan 01  22:43  Moon at Perigee: 360348 km"
                // "    03  11:03  FULL MOON"
                // "    03  18     Earth at Perihelion: 0.98330 AU"
                //
                // 1) riadok s mesiacom
                if (preg_match('~^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{2})\s+(.+)$~', trim($line), $mm)) {
                    $currentMonth = $mm[1];
                    $day = (int) $mm[2];
                    $rest = trim($mm[3]);
                }
                // 2) pokračovanie bez mesiaca (len deň + zvyšok)
                elseif (preg_match('~^(\d{2})\s+(.+)$~', trim($line), $mm)) {
                    if (!$currentMonth) {
                        continue;
                    }
                    $day  = (int) $mm[1];
                    $rest = trim($mm[2]);
                } else {
                    continue;
                }

                // čas + event:
                // "22:43  Moon at Perigee..."
                // "18     Earth at Perihelion..."
                // "23:01  Jupiter 3.7°S of Moon"
                //
                // Čas môže byť HH:MM alebo len HH
                $time = null;
                $eventText = null;

                if (preg_match('~^(\d{2}):(\d{2})\s+(.*)$~', $rest, $tm)) {
                    $time = sprintf('%02d:%02d', (int)$tm[1], (int)$tm[2]);
                    $eventText = trim($tm[3]);
                } elseif (preg_match('~^(\d{2})\s+(.*)$~', $rest, $tm)) {
                    $time = sprintf('%02d:00', (int)$tm[1]);
                    $eventText = trim($tm[2]);
                } else {
                    // nie je čas → preskoč, alebo nastav 00:00 (podľa potreby)
                    continue;
                }

                // Odfiltruj “rozbitý” event (niekedy je tam iba mesiacová hlavička)
                if ($eventText === '' || Str::lower($eventText) === 'event') {
                    continue;
                }

                $startAt = $this->makeDateTime($year, $currentMonth, $day, $time, $tz);
                if (!$startAt) {
                    continue;
                }

                $type = $this->guessType($eventText);

                $item = new EventCandidateData(
                    title: $eventText,
                    type: $type,
                    startAt: $startAt,
                    endAt: null,
                    maxAt: $startAt,
                    short: Str::limit($eventText, 120),
                    description: null,
                    sourceUid: $this->buildUid($year, $currentMonth, $day, $time, $eventText)
                );

                $items[] = $item;
            }
        }

        return $items;
    }

    private function extractYear(string $payload): ?int
    {
        // napr. v <title> je "Sky Event Almanac 2026 (Central European Time)"
        if (preg_match('~Sky Event Almanac\s+(\d{4})~i', $payload, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function makeDateTime(int $year, string $mon, int $day, string $time, string $tz): ?Carbon
    {
        $map = [
            'Jan' => 1,'Feb' => 2,'Mar' => 3,'Apr' => 4,'May' => 5,'Jun' => 6,
            'Jul' => 7,'Aug' => 8,'Sep' => 9,'Oct' => 10,'Nov' => 11,'Dec' => 12,
        ];

        $month = $map[$mon] ?? null;
        if (!$month) return null;

        [$h, $m] = array_map('intval', explode(':', $time));

        return Carbon::create($year, $month, $day, $h, $m, 0, $tz);
    }

    private function guessType(string $title): string
    {
        $t = Str::lower($title);

        return match (true) {
            Str::contains($t, 'meteor shower') => 'meteor_shower',
            Str::contains($t, 'eclipse') => 'eclipse',
            Str::contains($t, 'full moon') || Str::contains($t, 'new moon') ||
            Str::contains($t, 'first quarter') || Str::contains($t, 'last quarter') => 'moon_phase',
            Str::contains($t, 'solstice') || Str::contains($t, 'equinox') => 'season',
            Str::contains($t, 'perigee') || Str::contains($t, 'apogee') => 'moon_distance',
            Str::contains($t, 'conjunction') => 'conjunction',
            Str::contains($t, 'opposition') => 'opposition',
            default => 'event',
        };
    }

    private function buildUid(int $year, string $mon, int $day, string $time, string $title): string
    {
        return sprintf(
            '%d-%s-%02d-%s-%s',
            $year,
            strtolower($mon),
            $day,
            str_replace(':', '', $time),
            Str::slug(Str::limit($title, 60), '-')
        );
    }
}

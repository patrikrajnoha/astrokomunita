<?php

namespace App\Services\Crawlers\Astropixels;

use App\Services\Crawlers\CandidateItem;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Str;

class AstropixelsAlmanacParser
{
    /**
     * @return array<int, CandidateItem>
     */
    public function parse(
        string $html,
        int $year,
        string $sourceUrl,
        string $timezone = 'Europe/Bratislava',
    ): array {
        if (!preg_match_all('~<pre>(.*?)</pre>~si', $html, $matches) || empty($matches[1])) {
            throw new DomainException('Astropixels parser: expected <pre> almanac blocks were not found.');
        }

        $items = [];
        $lineIndex = 0;

        foreach ($matches[1] as $block) {
            $decoded = html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lines = preg_split("~\r\n|\n|\r~", $decoded) ?: [];

            $currentMonth = null;

            foreach ($lines as $line) {
                $lineIndex++;
                $lineTrimmed = trim((string) $line);

                if ($lineTrimmed === '' || Str::startsWith($lineTrimmed, ['Date', '(h:m)'])) {
                    continue;
                }

                if (preg_match('~^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{2})\s+(.+)$~', $lineTrimmed, $m)) {
                    $currentMonth = $m[1];
                    $day = (int) $m[2];
                    $rest = trim($m[3]);
                } elseif (preg_match('~^(\d{2})\s+(.+)$~', $lineTrimmed, $m)) {
                    if ($currentMonth === null) {
                        continue;
                    }
                    $day = (int) $m[1];
                    $rest = trim($m[2]);
                } else {
                    continue;
                }

                if (!preg_match('~^(\d{2})(?::(\d{2}))?\s+(.*)$~', $rest, $tm)) {
                    continue;
                }

                $hour = (int) $tm[1];
                $minute = isset($tm[2]) ? (int) $tm[2] : 0;
                $title = trim($tm[3]);
                if ($title === '') {
                    continue;
                }

                $localStart = $this->buildLocalDateTime($year, $currentMonth, $day, $hour, $minute, $timezone);
                if ($localStart === null) {
                    continue;
                }

                $rawPayload = [
                    'year' => $year,
                    'month' => $currentMonth,
                    'day' => $day,
                    'time_local' => sprintf('%02d:%02d', $hour, $minute),
                    'title' => $title,
                    'line_index' => $lineIndex,
                    'line' => $lineTrimmed,
                ];

                $items[] = new CandidateItem(
                    title: $title,
                    startsAtUtc: $localStart->utc(),
                    endsAtUtc: null,
                    description: $lineTrimmed,
                    sourceUrl: sprintf('%s#row-%d', $sourceUrl, $lineIndex),
                    externalId: hash('sha256', implode('|', [$year, $lineIndex, Str::lower($title), $localStart->format('Y-m-d H:i')])),
                    rawPayload: $rawPayload,
                    eventType: null,
                );
            }
        }

        if ($items === []) {
            throw new DomainException('Astropixels parser: table structure detected, but no event rows were parsed.');
        }

        return $items;
    }

    private function buildLocalDateTime(
        int $year,
        string $monthAbbrev,
        int $day,
        int $hour,
        int $minute,
        string $timezone,
    ): ?CarbonImmutable {
        $map = [
            'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
            'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
            'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
        ];

        $month = $map[$monthAbbrev] ?? null;
        if ($month === null) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, $hour, $minute, 0, $timezone);
    }
}

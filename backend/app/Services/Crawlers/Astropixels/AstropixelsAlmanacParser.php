<?php

namespace App\Services\Crawlers\Astropixels;

use App\Services\Crawlers\CandidateItem;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;
use Illuminate\Support\Str;

class AstropixelsAlmanacParser
{
    private const SOURCE_TIMEZONE = '+01:00';

    private const MONTH_MAP = [
        'Jan' => 1,
        'Feb' => 2,
        'Mar' => 3,
        'Apr' => 4,
        'May' => 5,
        'Jun' => 6,
        'Jul' => 7,
        'Aug' => 8,
        'Sep' => 9,
        'Oct' => 10,
        'Nov' => 11,
        'Dec' => 12,
    ];

    private const MONTH_PATTERN = '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)';
    private const MAX_DIAGNOSTICS = 40;
    private const MAX_DIAGNOSTIC_LENGTH = 240;

    /**
     * Astropixels publishes separate fixed-offset almanacs (GMT/CET/EET...), so CET rows stay UTC+1 year-round.
     */
    public function parse(
        string $html,
        int $year,
        string $sourceUrl,
        string $timezone = self::SOURCE_TIMEZONE,
    ): AstropixelsParseResult {
        $diagnostics = [];

        $xpath = $this->createXPath($html, $diagnostics);
        $table = $this->resolveAlmanacTable($xpath, $diagnostics);

        if (!$table) {
            throw new DomainException($this->formatFatal(
                'Astropixels parser: table not found for "Sky Event Almanac" section.',
                $diagnostics
            ));
        }

        $items = [];
        $preBlocks = $xpath->query('.//pre', $table);
        if ($preBlocks === false || $preBlocks->length === 0) {
            throw new DomainException($this->formatFatal(
                'Astropixels parser: event table found, but <pre> blocks are missing.',
                $diagnostics
            ));
        }

        foreach ($preBlocks as $preIndex => $pre) {
            $decoded = html_entity_decode((string) $pre->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lines = preg_split('~\R~u', $decoded) ?: [];
            $rawLines = preg_split('~\R~u', $this->innerHtml($pre)) ?: [];
            $currentMonth = null;

            foreach ($lines as $lineIndex => $line) {
                $lineTrimmed = $this->normalizeWhitespace((string) $line);
                if ($this->isSkippableLine($lineTrimmed)) {
                    continue;
                }

                $parsed = $this->parseEventLine(
                    $lineTrimmed,
                    $currentMonth,
                    $preIndex + 1,
                    $lineIndex + 1,
                    $diagnostics
                );

                if ($parsed === null) {
                    continue;
                }

                [$monthAbbrev, $day, $hour, $minute, $title, $rowSignature] = $parsed;

                $localStart = $this->buildLocalDateTime($year, $monthAbbrev, $day, $hour, $minute, $timezone);
                if ($localStart === null) {
                    $this->addDiagnostic(
                        $diagnostics,
                        "invalid date at pre#{$preIndex}:{$lineIndex} ({$monthAbbrev} {$day} {$hour}:{$minute})"
                    );
                    continue;
                }

                $startsAtUtc = $localStart->utc();
                $rawLine = $rawLines[$lineIndex] ?? '';
                $resolvedHref = $this->extractHref($rawLine, $sourceUrl);
                $rowUrl = $resolvedHref ?: $sourceUrl;
                $normalizedTitle = $this->normalizeWhitespace($title);
                $externalId = $resolvedHref
                    ? 'href:' . hash('sha256', $resolvedHref)
                    : $this->buildStableKey($normalizedTitle, $startsAtUtc, $sourceUrl, $year, $rowSignature);

                $rawPayload = [
                    'year' => $year,
                    'month' => $monthAbbrev,
                    'day' => $day,
                    'time_local' => sprintf('%02d:%02d', $hour, $minute),
                    'source_timezone' => $timezone,
                    'title' => $normalizedTitle,
                    'row_signature' => $rowSignature,
                    'source_href' => $resolvedHref,
                    'line' => $lineTrimmed,
                ];

                $items[] = new CandidateItem(
                    title: $normalizedTitle,
                    startsAtUtc: $startsAtUtc,
                    endsAtUtc: null,
                    description: $lineTrimmed,
                    sourceUrl: $rowUrl,
                    externalId: $externalId,
                    rawPayload: $rawPayload,
                    eventType: $this->inferEventType($normalizedTitle),
                    timeType: 'peak',
                    timePrecision: 'exact',
                );
            }
        }

        if ($items === []) {
            throw new DomainException($this->formatFatal(
                'Astropixels parser: table structure detected, but no event rows were parsed.',
                $diagnostics
            ));
        }

        return new AstropixelsParseResult(
            items: $items,
            diagnostics: $diagnostics
        );
    }

    private function createXPath(string $html, array &$diagnostics): DOMXPath
    {
        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            throw new DomainException('Astropixels parser: failed to parse HTML document.');
        }

        if (count($errors) > 0) {
            $this->addDiagnostic($diagnostics, 'dom parser warnings=' . count($errors));
        }

        return new DOMXPath($dom);
    }

    private function resolveAlmanacTable(DOMXPath $xpath, array &$diagnostics): ?DOMElement
    {
        $tables = $xpath->query('//table');
        if ($tables === false || $tables->length === 0) {
            $this->addDiagnostic($diagnostics, 'no table nodes found');
            return null;
        }

        $candidates = [];
        foreach ($tables as $table) {
            $text = mb_strtolower($this->normalizeWhitespace((string) $table->textContent), 'UTF-8');
            $hasPre = (($xpath->query('.//pre', $table)?->length ?? 0) > 0);
            if (!$hasPre) {
                continue;
            }

            $score = 0;
            if (str_contains($text, 'sky event almanac')) {
                $score += 2;
            }
            if (str_contains($text, 'central european time')) {
                $score += 1;
            }
            if (str_contains($text, 'date') && str_contains($text, 'event')) {
                $score += 1;
            }
            if ($table->hasAttribute('class') && str_contains((string) $table->getAttribute('class'), 'datatab')) {
                $score += 2;
            }

            $candidates[] = ['node' => $table, 'score' => $score];
        }

        if ($candidates === []) {
            $this->addDiagnostic($diagnostics, 'tables found but none include <pre> almanac rows');
            return null;
        }

        usort($candidates, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $best = $candidates[0];
        if ($best['score'] <= 0) {
            $this->addDiagnostic($diagnostics, 'almanac table heuristic score <= 0');
            return null;
        }

        return $best['node'];
    }

    /**
     * @return array{0:string,1:int,2:int,3:int,4:string,5:string}|null
     */
    private function parseEventLine(
        string $line,
        ?string &$currentMonth,
        int $preIndex,
        int $lineNumber,
        array &$diagnostics
    ): ?array {
        if (!preg_match('~^(?:' . self::MONTH_PATTERN . '\s+)?(\d{1,2})\s+(.+)$~', $line, $m)) {
            if (preg_match('~^\d{1,2}\s+~', $line) || preg_match('~^' . self::MONTH_PATTERN . '\s+~', $line)) {
                $this->addDiagnostic($diagnostics, "unexpected row format pre#{$preIndex}:{$lineNumber}");
            }
            return null;
        }

        $monthAbbrev = $m[1] ?? null;
        if ($monthAbbrev !== null && $monthAbbrev !== '') {
            $currentMonth = $monthAbbrev;
        }
        if ($currentMonth === null) {
            $this->addDiagnostic($diagnostics, "day row without month pre#{$preIndex}:{$lineNumber}");
            return null;
        }

        $day = (int) $m[2];
        $rest = $this->normalizeWhitespace($m[3]);

        if (!preg_match('~^(\d{1,2})(?::(\d{2}))?\s+(.*)$~', $rest, $tm)) {
            $this->addDiagnostic($diagnostics, "unexpected column count pre#{$preIndex}:{$lineNumber}");
            return null;
        }

        $hour = (int) $tm[1];
        $minute = isset($tm[2]) ? (int) $tm[2] : 0;
        $title = $this->normalizeWhitespace($tm[3]);

        if ($hour > 23 || $minute > 59 || $title === '') {
            $this->addDiagnostic($diagnostics, "invalid time/title pre#{$preIndex}:{$lineNumber}");
            return null;
        }

        $rowSignature = hash('sha256', implode('|', [
            mb_strtolower($currentMonth, 'UTF-8'),
            $day,
            sprintf('%02d:%02d', $hour, $minute),
            mb_strtolower($title, 'UTF-8'),
        ]));

        return [$currentMonth, $day, $hour, $minute, $title, $rowSignature];
    }

    private function extractHref(string $lineHtml, string $fallbackUrl): ?string
    {
        if (!preg_match('~href=["\']([^"\']+)["\']~i', $lineHtml, $m)) {
            return null;
        }

        $href = trim($m[1]);
        if ($href === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '#')) {
            return $fallbackUrl . $href;
        }

        $parts = parse_url($fallbackUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $basePath = $dir === '' ? '' : $dir;

        return "{$scheme}://{$host}{$basePath}/{$href}";
    }

    private function buildStableKey(
        string $title,
        CarbonImmutable $startsAtUtc,
        string $sourceUrl,
        int $year,
        string $rowSignature
    ): string {
        return hash('sha256', implode('|', [
            mb_strtolower($title, 'UTF-8'),
            $startsAtUtc->format('Y-m-d H:i:s'),
            $sourceUrl,
            $year,
            $rowSignature,
        ]));
    }

    private function buildLocalDateTime(
        int $year,
        string $monthAbbrev,
        int $day,
        int $hour,
        int $minute,
        string $timezone,
    ): ?CarbonImmutable {
        $month = self::MONTH_MAP[$monthAbbrev] ?? null;
        if ($month === null) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, $hour, $minute, 0, $timezone);
    }

    private function inferEventType(string $title): ?string
    {
        $value = mb_strtolower($title, 'UTF-8');

        if (str_contains($value, 'solar eclipse')) {
            return 'eclipse_solar';
        }
        if (str_contains($value, 'lunar eclipse')) {
            return 'eclipse_lunar';
        }
        if (str_contains($value, 'eclipse')) {
            return 'eclipse';
        }
        if (str_contains($value, 'meteor shower') || str_contains($value, 'meteor')) {
            return 'meteor_shower';
        }
        if (str_contains($value, 'conjunction')) {
            return 'conjunction';
        }
        if (str_contains($value, 'opposition')) {
            return 'planetary_event';
        }
        if (str_contains($value, 'comet')) {
            return 'comet';
        }
        if (str_contains($value, 'asteroid')) {
            return 'asteroid';
        }
        if (
            str_contains($value, 'perihelion')
            || str_contains($value, 'aphelion')
            || str_contains($value, 'equinox')
            || str_contains($value, 'solstice')
            || str_contains($value, 'occn.')
        ) {
            return 'planetary_event';
        }

        return null;
    }

    private function isSkippableLine(string $line): bool
    {
        if ($line === '') {
            return true;
        }

        return Str::startsWith($line, [
            'Date',
            '(h:m)',
            'January - June',
            'July - December',
        ]);
    }

    private function normalizeWhitespace(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }

        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }

    private function addDiagnostic(array &$diagnostics, string $message): void
    {
        if (count($diagnostics) >= self::MAX_DIAGNOSTICS) {
            return;
        }

        $diagnostics[] = Str::limit($this->normalizeWhitespace($message), self::MAX_DIAGNOSTIC_LENGTH, '');
    }

    private function formatFatal(string $message, array $diagnostics): string
    {
        if ($diagnostics === []) {
            return $message;
        }

        return $message . ' diagnostics=' . implode('; ', array_slice($diagnostics, 0, 6));
    }
}

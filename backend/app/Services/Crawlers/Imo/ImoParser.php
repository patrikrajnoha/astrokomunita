<?php

namespace App\Services\Crawlers\Imo;

use App\Services\Crawlers\CandidateItem;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

class ImoParser
{
    private const MAX_DIAGNOSTICS = 60;

    private const MONTH_MAP = [
        'jan' => 1,
        'january' => 1,
        'feb' => 2,
        'february' => 2,
        'mar' => 3,
        'march' => 3,
        'apr' => 4,
        'april' => 4,
        'may' => 5,
        'jun' => 6,
        'june' => 6,
        'jul' => 7,
        'july' => 7,
        'aug' => 8,
        'august' => 8,
        'sep' => 9,
        'sept' => 9,
        'september' => 9,
        'oct' => 10,
        'october' => 10,
        'nov' => 11,
        'november' => 11,
        'dec' => 12,
        'december' => 12,
    ];

    public function parse(string $html, int $year, string $sourceUrl): ImoParseResult
    {
        $diagnostics = [];
        $xpath = $this->createXPath($html, $diagnostics);

        $nodes = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " shower ") and contains(concat(" ", normalize-space(@class), " "), " media ")]'
        );

        if ($nodes === false || $nodes->length === 0) {
            throw new DomainException('IMO parser: shower blocks not found.');
        }

        $items = [];

        /** @var DOMElement $node */
        foreach ($nodes as $index => $node) {
            try {
                $candidate = $this->parseShowerNode($xpath, $node, $year, $sourceUrl);
                if ($candidate === null) {
                    continue;
                }

                $items[] = $candidate;
            } catch (Throwable $exception) {
                $this->addDiagnostic(
                    $diagnostics,
                    sprintf('shower block %d skipped: %s', $index + 1, $exception->getMessage())
                );
            }
        }

        if ($items === []) {
            throw new DomainException('IMO parser: no shower candidates parsed for requested year.');
        }

        return new ImoParseResult(
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

        if (! $loaded) {
            throw new DomainException('IMO parser: failed to parse HTML document.');
        }

        if (count($errors) > 0) {
            $this->addDiagnostic($diagnostics, 'dom parser warnings=' . count($errors));
        }

        return new DOMXPath($dom);
    }

    private function parseShowerNode(DOMXPath $xpath, DOMElement $node, int $year, string $sourceUrl): ?CandidateItem
    {
        $titleRaw = $this->normalizeWhitespace((string) ($xpath->evaluate('string(.//h3[contains(@class, "media-heading")])', $node) ?? ''));
        if ($titleRaw === '') {
            throw new DomainException('missing title');
        }

        $title = $this->normalizeTitle($titleRaw);

        $peakText = $this->normalizeWhitespace((string) ($xpath->evaluate('string(.//span[contains(@class, "shower_peak")]//strong)', $node) ?? ''));
        if ($peakText === '') {
            throw new DomainException('missing peak block');
        }

        $peakParts = $this->extractPeakDateParts($peakText);
        if ($peakParts === null) {
            throw new DomainException('invalid peak text');
        }

        if ($peakParts['year'] !== $year) {
            return null;
        }

        $description = $this->normalizeWhitespace((string) ($xpath->evaluate('string((.//p)[1])', $node) ?? ''));
        if ($description === '') {
            $description = null;
        }

        $detailsText = $this->normalizeWhitespace((string) ($xpath->evaluate('string((.//p)[2])', $node) ?? ''));
        $zhr = $this->extractZhr($detailsText);
        $radiant = $this->extractRadiant($detailsText);
        $velocityKmh = $this->extractVelocityKmh($detailsText);
        $utTime = $this->extractUtTime($description);

        $startsAtUtc = CarbonImmutable::create(
            $peakParts['year'],
            $peakParts['month'],
            $peakParts['day'],
            $utTime['hour'],
            $utTime['minute'],
            0,
            'UTC'
        );

        $headingId = trim((string) $node->getAttribute('id'));
        $itemSourceUrl = $headingId !== ''
            ? rtrim($sourceUrl, '/') . '#shower-' . $headingId
            : $sourceUrl;

        $externalId = $this->buildExternalId($title, $startsAtUtc);

        return new CandidateItem(
            title: $title,
            startsAtUtc: $startsAtUtc,
            endsAtUtc: null,
            description: $description,
            sourceUrl: $itemSourceUrl,
            externalId: $externalId,
            rawPayload: [
                'peak_text' => $peakText,
                'peak_year' => $peakParts['year'],
                'peak_month' => $peakParts['month'],
                'peak_day' => $peakParts['day'],
                'peak_time_utc' => sprintf('%02d:%02d', $utTime['hour'], $utTime['minute']),
                'peak_time_known' => $utTime['known'],
                'zhr' => $zhr,
                'radiant' => $radiant,
                'velocity_km_s' => $velocityKmh,
            ],
            eventType: 'meteor_shower',
            canonicalKey: null,
            confidenceScore: null,
            matchedSources: null,
            timeType: 'peak',
            timePrecision: $utTime['known'] ? 'exact' : 'unknown',
        );
    }

    /**
     * @return array{year:int,month:int,day:int}|null
     */
    private function extractPeakDateParts(string $peakText): ?array
    {
        if (
            preg_match(
                '/([A-Za-z]{3,9})\s+(\d{1,2})(?:\s*-\s*(\d{1,2}))?,\s*(\d{4})/u',
                $peakText,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $monthKey = strtolower(trim((string) ($matches[1] ?? '')));
        $month = self::MONTH_MAP[$monthKey] ?? null;
        if ($month === null) {
            return null;
        }

        $startDay = (int) ($matches[2] ?? 0);
        $endDay = isset($matches[3]) ? (int) $matches[3] : null;
        $day = $endDay ?: $startDay;
        $year = (int) ($matches[4] ?? 0);

        if ($day < 1 || $day > 31 || $year < 1900 || $year > 2200) {
            return null;
        }

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }

    /**
     * @return array{hour:int,minute:int,known:bool}
     */
    private function extractUtTime(?string $description): array
    {
        $text = (string) ($description ?? '');

        if (preg_match('/\bnear\s+(\d{1,2})(?::(\d{2}))?\s*UT\b/i', $text, $matches) === 1) {
            $hour = (int) ($matches[1] ?? 0);
            $minute = isset($matches[2]) ? (int) $matches[2] : 0;

            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return ['hour' => $hour, 'minute' => $minute, 'known' => true];
            }
        }

        return ['hour' => 0, 'minute' => 0, 'known' => false];
    }

    private function extractZhr(string $detailsText): ?int
    {
        if (preg_match('/\bZHR\b[^0-9]{0,12}(\d{1,3})/i', $detailsText, $matches) !== 1) {
            return null;
        }

        $value = (int) ($matches[1] ?? 0);
        return $value > 0 ? $value : null;
    }

    private function extractRadiant(string $detailsText): ?string
    {
        if (preg_match('/\bRadiant\b:\s*([0-9]{1,2}:[0-9]{2}\s*[+\-][0-9]{1,2})/i', $detailsText, $matches) !== 1) {
            return null;
        }

        $value = $this->normalizeWhitespace((string) ($matches[1] ?? ''));
        return $value !== '' ? $value : null;
    }

    private function extractVelocityKmh(string $detailsText): ?float
    {
        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*km\/sec/i', $detailsText, $matches) !== 1) {
            return null;
        }

        $value = str_replace(',', '.', (string) ($matches[1] ?? ''));
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 1);
    }

    private function normalizeTitle(string $value): string
    {
        $title = $this->normalizeWhitespace($value);
        $title = preg_replace('/\s*\(\s*([A-Z0-9]{2,5})\s*\)\s*$/u', ' ($1)', $title) ?? $title;
        return trim($title);
    }

    private function buildExternalId(string $title, CarbonImmutable $startsAtUtc): string
    {
        $slug = Str::slug($title, '-');
        $date = $startsAtUtc->format('Ymd');

        return sprintf('imo:%s:%s', $slug !== '' ? $slug : 'meteor-shower', $date);
    }

    private function normalizeWhitespace(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', trim($decoded)) ?? trim($decoded);
        return trim($normalized);
    }

    private function addDiagnostic(array &$diagnostics, string $message): void
    {
        if (count($diagnostics) >= self::MAX_DIAGNOSTICS) {
            return;
        }

        $diagnostics[] = Str::limit($this->normalizeWhitespace($message), 240, '');
    }
}

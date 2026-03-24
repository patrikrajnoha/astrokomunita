<?php

namespace App\Services\Events;

use Illuminate\Support\Str;

/**
 * Checks AI-generated event descriptions for safety and factual drift.
 *
 * All methods are pure (no I/O, no side effects) and depend only on their inputs.
 */
class EventDescriptionQualityChecker
{
    private const NUMERIC_TOKEN_PATTERN = '/\b\d{1,4}(?:[.,:]\d{1,4}){0,2}\b/u';
    private const ISO_TIMESTAMP_PATTERN = '/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+\-]\d{2}:\d{2})\b/u';
    private const CELESTIAL_TERMS = [
        'slnko',
        'mesiac',
        'zem',
        'merkur',
        'venus',
        'mars',
        'jupiter',
        'saturn',
        'uran',
        'neptun',
        'pluto',
        'regulus',
        'spica',
        'antares',
        'pollux',
        'pleiades',
        'plejady',
    ];

    public function passesSafetyGuards(string $title, string $baseDescription, string $candidateDescription): bool
    {
        $inputContext = $title . "\n" . $baseDescription;

        if ($this->introducesUnknownNumericTokens($inputContext, $candidateDescription)) {
            return false;
        }

        if ($this->mentionsUnexpectedCelestialTerms($inputContext, $candidateDescription)) {
            return false;
        }

        return true;
    }

    /**
     * @param array{why_interesting:string,how_to_observe:string} $insights
     * @param array<string,mixed> $factualPack
     * @return array<int,string>
     */
    public function detectHumanizedFactualDrift(
        string $description,
        string $short,
        array $insights,
        array $factualPack
    ): array {
        $errors = [];
        $text = trim(implode("\n", array_filter([
            $description,
            $short,
            (string) ($insights['why_interesting'] ?? ''),
            (string) ($insights['how_to_observe'] ?? ''),
        ], static fn (string $value): bool => $value !== '')));

        if ($text === '') {
            return ['factual_drift:empty_output'];
        }

        if (preg_match(self::ISO_TIMESTAMP_PATTERN, $text) === 1) {
            $errors[] = 'factual_drift:iso_timestamp';
        }

        $keyValues = is_array($factualPack['key_values'] ?? null)
            ? (array) $factualPack['key_values']
            : [];

        $expectedDistanceKm = $keyValues['distance_km'] ?? null;
        if (is_numeric($expectedDistanceKm) && $this->hasDistanceKmDrift($text, (float) $expectedDistanceKm)) {
            $errors[] = 'factual_drift:distance_km';
        }

        $expectedSeparationDeg = $keyValues['separation_deg'] ?? null;
        if (is_numeric($expectedSeparationDeg) && $this->hasSeparationDegDrift($text, (float) $expectedSeparationDeg)) {
            $errors[] = 'factual_drift:separation_deg';
        }

        return array_values(array_unique($errors));
    }

    private function introducesUnknownNumericTokens(string $inputContext, string $outputContext): bool
    {
        $inputTokens = $this->extractNumericTokens($inputContext);
        $outputTokens = $this->extractNumericTokens($outputContext);

        if ($outputTokens === []) {
            return false;
        }

        $inputLookup = array_fill_keys($inputTokens, true);
        foreach ($outputTokens as $token) {
            if (! isset($inputLookup[$token])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function extractNumericTokens(string $text): array
    {
        preg_match_all(self::NUMERIC_TOKEN_PATTERN, $text, $matches);

        $tokens = [];
        foreach (($matches[0] ?? []) as $token) {
            $value = strtolower(trim((string) $token));
            if ($value === '') {
                continue;
            }

            $value = str_replace(',', '.', $value);
            $value = preg_replace('/\s+/u', '', $value) ?? $value;
            $tokens[] = $value;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    private function mentionsUnexpectedCelestialTerms(string $inputContext, string $outputContext): bool
    {
        $inputNormalized = Str::of($inputContext)->ascii()->lower()->value();
        $outputNormalized = Str::of($outputContext)->ascii()->lower()->value();

        $allowed = [];
        $used = [];

        foreach (self::CELESTIAL_TERMS as $term) {
            if (str_contains($inputNormalized, $term)) {
                $allowed[$term] = true;
            }

            if (str_contains($outputNormalized, $term)) {
                $used[$term] = true;
            }
        }

        foreach (array_keys($used) as $term) {
            if (! isset($allowed[$term])) {
                return true;
            }
        }

        return false;
    }

    private function hasDistanceKmDrift(string $text, float $expectedDistanceKm): bool
    {
        preg_match_all('/([0-9][0-9\s.,]*)\s*km\b/iu', $text, $matches);
        $tokens = $matches[1] ?? [];
        if (! is_array($tokens) || $tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            $candidate = $this->parseLooseNumber((string) $token);
            if ($candidate === null) {
                continue;
            }

            if (abs($candidate - $expectedDistanceKm) > 0.5) {
                return true;
            }
        }

        return false;
    }

    private function hasSeparationDegDrift(string $text, float $expectedSeparationDeg): bool
    {
        preg_match_all('/([0-9]+(?:[.,][0-9]+)?)\s*(?:\x{00B0}|deg|stupn(?:e|ov)?)\b/iu', $text, $matches);
        $tokens = $matches[1] ?? [];
        if (! is_array($tokens) || $tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            $candidate = $this->parseLooseNumber((string) $token);
            if ($candidate === null) {
                continue;
            }

            if (abs($candidate - $expectedSeparationDeg) > 0.05) {
                return true;
            }
        }

        return false;
    }

    private function parseLooseNumber(string $value): ?float
    {
        $normalized = preg_replace('/\s+/u', '', trim($value)) ?? trim($value);
        $normalized = str_replace(',', '.', $normalized);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}

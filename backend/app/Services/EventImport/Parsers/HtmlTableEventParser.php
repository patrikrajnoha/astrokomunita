<?php

namespace App\Services\EventImport\Parsers;

use App\Services\EventImport\EventCandidateData;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;

class HtmlTableEventParser implements EventSourceParser
{
    public function parse(string $payload): array
    {
        $dom = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($payload);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tr');
        if ($rows === false) {
            return [];
        }

        $headerMap = [];
        $candidates = [];

        foreach ($rows as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }

            $cells = $xpath->query('th|td', $row);
            if ($cells === false || $cells->length === 0) {
                continue;
            }

            $cellTexts = [];
            foreach ($cells as $cell) {
                $cellTexts[] = trim($cell->textContent);
            }

            if ($this->looksLikeHeaderRow($cells, $cellTexts)) {
                $headerMap = $this->buildHeaderMap($cellTexts);
                continue;
            }

            $data = $this->buildDataFromRow($cellTexts, $headerMap);
            if ($data === null) {
                continue;
            }

            $candidates[] = EventCandidateData::fromArray([
                'title' => $data['title'],
                'type' => $data['type'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'max_at' => $data['max_at'],
                'short' => $data['short'],
                'description' => $data['description'],
                'source_uid' => $row->getAttribute('data-uid') ?: null,
            ]);
        }

        return $candidates;
    }

    private function looksLikeHeaderRow($cells, array $cellTexts): bool
    {
        $hasTh = false;
        foreach ($cells as $cell) {
            if ($cell instanceof DOMElement && $cell->tagName === 'th') {
                $hasTh = true;
                break;
            }
        }

        if ($hasTh) {
            return true;
        }

        $combined = strtolower(implode(' ', $cellTexts));
        return str_contains($combined, 'title')
            || str_contains($combined, 'názov')
            || str_contains($combined, 'start')
            || str_contains($combined, 'zač');
    }

    private function buildHeaderMap(array $cellTexts): array
    {
        $map = [];

        foreach ($cellTexts as $index => $text) {
            $normalized = strtolower(trim($text));
            $map[$index] = match (true) {
                str_contains($normalized, 'title'),
                str_contains($normalized, 'názov') => 'title',
                str_contains($normalized, 'type'),
                str_contains($normalized, 'typ') => 'type',
                str_contains($normalized, 'start'),
                str_contains($normalized, 'zač') => 'start_at',
                str_contains($normalized, 'end'),
                str_contains($normalized, 'do') => 'end_at',
                str_contains($normalized, 'max') => 'max_at',
                str_contains($normalized, 'short'),
                str_contains($normalized, 'stru') => 'short',
                str_contains($normalized, 'desc'),
                str_contains($normalized, 'popis') => 'description',
                default => null,
            };
        }

        return $map;
    }

    private function buildDataFromRow(array $cellTexts, array $headerMap): ?array
    {
        $data = [
            'title' => null,
            'type' => null,
            'start_at' => null,
            'end_at' => null,
            'max_at' => null,
            'short' => null,
            'description' => null,
        ];

        foreach ($cellTexts as $index => $text) {
            $key = $headerMap[$index] ?? $this->fallbackKey($index);
            if ($key === null) {
                continue;
            }

            $data[$key] = match ($key) {
                'start_at', 'end_at', 'max_at' => $this->parseDate($text),
                default => $text !== '' ? $text : null,
            };
        }

        if ($data['title'] === null) {
            return null;
        }

        return $data;
    }

    private function fallbackKey(int $index): ?string
    {
        return match ($index) {
            0 => 'title',
            1 => 'type',
            2 => 'start_at',
            3 => 'end_at',
            4 => 'max_at',
            5 => 'short',
            6 => 'description',
            default => null,
        };
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}(?:\s+\d{2}:\d{2})?/', $value, $matches) !== 1) {
            return null;
        }

        return Carbon::parse($matches[0]);
    }
}

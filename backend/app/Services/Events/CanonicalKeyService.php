<?php

namespace App\Services\Events;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CanonicalKeyService
{
    /**
     * @var array<string,array<int,string>>
     */
    private const METEOR_SHOWER_ALIASES = [
        'quadrantids' => ['quadrantids', 'quadrantid', 'quadrantidy', 'qua'],
        'lyrids' => ['lyrids', 'lyrid', 'lyridy', 'lyr'],
        'eta aquariids' => ['eta aquariids', 'eta aquariid', 'eta akvaridy', 'eta akvarid'],
        'delta aquariids' => ['delta aquariids', 'delta aquariid', 'delta akvaridy', 'delta akvarid'],
        'perseids' => ['perseids', 'perseid', 'perseidy', 'per'],
        'orionids' => ['orionids', 'orionid', 'orionidy', 'ori'],
        'north taurids' => ['north taurids', 'northern taurids', 'severne tauridy', 'nta'],
        'south taurids' => ['south taurids', 'southern taurids', 'juzne tauridy', 'sta'],
        'leonids' => ['leonids', 'leonid', 'leonidy', 'leo'],
        'geminids' => ['geminids', 'geminid', 'geminidy', 'gem'],
        'ursids' => ['ursids', 'ursid', 'ursidy', 'urs'],
    ];

    public function make(string $type, ?CarbonInterface $date, string $title): string
    {
        $normalizedType = $this->normalize((string) $type);
        $normalizedTitle = $this->normalize($title);

        if ($normalizedType === 'meteor shower') {
            $normalizedTitle = $this->normalizeMeteorShowerTitle($title, $normalizedTitle);
        }

        $parts = [$normalizedType];
        if ($date !== null) {
            $parts[] = $date->utc()->toDateString();
        }
        $parts[] = $normalizedTitle;

        return implode('|', $parts);
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeMeteorShowerTitle(string $title, string $fallback): string
    {
        $ascii = $this->normalizeAscii($title);
        if ($ascii === '') {
            return $fallback;
        }

        foreach (self::METEOR_SHOWER_ALIASES as $canonicalName => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->containsAlias($ascii, $alias)) {
                    return $canonicalName;
                }
            }
        }

        return $fallback;
    }

    private function normalizeAscii(string $value): string
    {
        $normalized = Str::of($value)->ascii()->lower()->value();
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function containsAlias(string $text, string $alias): bool
    {
        $needle = trim($alias);
        if ($needle === '') {
            return false;
        }

        if (str_contains($needle, ' ')) {
            return str_contains(" {$text} ", " {$needle} ");
        }

        return preg_match('/\b'.preg_quote($needle, '/').'\b/', $text) === 1;
    }
}

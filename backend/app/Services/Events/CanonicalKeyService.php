<?php

namespace App\Services\Events;

use Carbon\CarbonInterface;

class CanonicalKeyService
{
    public function make(string $type, ?CarbonInterface $date, string $title): string
    {
        $normalizedType = $this->normalize((string) $type);
        $normalizedTitle = $this->normalize($title);

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
}

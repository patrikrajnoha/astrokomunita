<?php

namespace App\Services\Events;

use Illuminate\Support\Str;

/**
 * Enriches and sanitizes AI-generated event description text.
 *
 * All methods are pure (no I/O, no side effects) and depend only on their inputs.
 */
class EventDescriptionEnricher
{
    public function enrichDescription(
        string $description,
        string $startLocal,
        string $endLocal,
        string $maxLocal
    ): string {
        $value = trim($description);
        if ($value === '') {
            return '';
        }

        $sentences = $this->splitSentences($value);
        if ($sentences === []) {
            return '';
        }

        $sentences = array_slice($sentences, 0, 3);
        $lower = Str::lower(implode(' ', $sentences));

        if (! $this->containsAny($lower, ['pozorovat', 'sledovat', 'viditel'])) {
            if (count($sentences) < 3) {
                $sentences[] = 'Oplati sa ho sledovat, pretoze pomaha lepsie pochopit dianie na oblohe.';
            } else {
                $sentences[1] = 'Oplati sa ho sledovat, pretoze pomaha lepsie pochopit dianie na oblohe.';
            }
        }

        $lower = Str::lower(implode(' ', $sentences));
        if (! $this->containsAny($lower, ['zaujimav'])) {
            if (count($sentences) < 3) {
                $sentences[] = 'Zaujimavostou je, ze podobne ukazy pomahaju sledovat pohyb telies na oblohe.';
            } else {
                $sentences[2] = 'Zaujimavostou je, ze podobne ukazy pomahaju sledovat pohyb telies na oblohe.';
            }
        }

        $lower = Str::lower(implode(' ', $sentences));
        if (! $this->containsVisibilityCue($lower)) {
            $visibilitySentence = $this->buildVisibilitySentence($startLocal, $endLocal, $maxLocal);
            if (count($sentences) < 3) {
                $sentences[] = $visibilitySentence;
            } else {
                $visibilityFragment = lcfirst(rtrim($visibilitySentence, '.! '));
                $sentences[2] = rtrim($sentences[2], '.! ') . '; ' . $visibilityFragment . '.';
            }
        }

        if (count($sentences) < 2) {
            $sentences[] = 'Pozorovanie pomaha lepsie pochopit dynamiku oblohy.';
        }

        $sentences = array_map([$this, 'ensureSentenceEnding'], array_slice($sentences, 0, 3));
        return $this->sanitizeText(implode(' ', $sentences), 500);
    }

    /**
     * @return array<int,string>
     */
    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $parts = array_map(static fn (string $item): string => trim($item), $parts);
        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }

    private function containsVisibilityCue(string $text): bool
    {
        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/u', $text) === 1) {
            return true;
        }

        if (preg_match('/\b\d{1,2}:\d{2}\b/u', $text) === 1) {
            return true;
        }

        return $this->containsAny($text, [
            'viditel',
            'vecer',
            'rano',
            'v noci',
            'po zapade',
            'pred svitanim',
            'pozorovatel',
        ]);
    }

    private function buildVisibilitySentence(string $startLocal, string $endLocal, string $maxLocal): string
    {
        foreach ([$maxLocal, $startLocal, $endLocal] as $value) {
            if ($value !== 'n/a') {
                return "Najlepsia viditelnost je okolo {$value} (lokalny cas).";
            }
        }

        return 'Cas viditelnosti zavisi od polohy pozorovatela.';
    }

    private function ensureSentenceEnding(string $sentence): string
    {
        $value = trim($sentence);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[.!?]$/u', $value) === 1) {
            return $value;
        }

        return $value . '.';
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return Str::limit(trim($plain), $maxLength, '');
    }
}

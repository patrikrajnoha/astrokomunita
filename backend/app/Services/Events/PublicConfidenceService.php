<?php

namespace App\Services\Events;

use App\Models\Event;

class PublicConfidenceService
{
    /**
     * @return array{
     *   score:int|null,
     *   sources_count:int|null,
     *   level:string,
     *   label:string,
     *   reason:string
     * }
     */
    public function badgeFor(Event $event): array
    {
        $score = $this->normalizeScore($event->confidence_score);
        $sourcesCount = $this->resolveSourcesCount($event);

        if ($score === null) {
            return [
                'score' => null,
                'sources_count' => $sourcesCount,
                'level' => 'unknown',
                'label' => 'Neznáme',
                'reason' => 'Nie sú dostupné údaje o dôveryhodnosti.',
            ];
        }

        $verifiedScore = (int) config('events.public_confidence.verified_score', 80);
        $partialScore = (int) config('events.public_confidence.partial_score', 60);
        $verifiedMinSources = (int) config('events.public_confidence.verified_min_sources', 2);
        $partialMinSources = (int) config('events.public_confidence.partial_min_sources', 1);
        $safeSourcesCount = $sourcesCount ?? 0;

        if ($score >= $verifiedScore && $safeSourcesCount >= $verifiedMinSources) {
            return $this->makeBadge('verified', 'Overené', 'Potvrdené viacerými zdrojmi.', $score, $sourcesCount);
        }

        if ($score >= $partialScore && $safeSourcesCount >= $partialMinSources) {
            return $this->makeBadge('partial', 'Čiastočne overené', 'Potvrdené aspoň jedným zdrojom.', $score, $sourcesCount);
        }

        return $this->makeBadge('low', 'Nízka dôvera', 'Nedostatočné potvrdenie z viacerých zdrojov.', $score, $sourcesCount);
    }

    private function makeBadge(string $level, string $label, string $reason, int $score, ?int $sourcesCount): array
    {
        return [
            'score' => $score,
            'sources_count' => $sourcesCount,
            'level' => $level,
            'label' => $label,
            'reason' => $reason,
        ];
    }

    private function normalizeScore(mixed $rawScore): ?int
    {
        if ($rawScore === null || !is_numeric((string) $rawScore)) {
            return null;
        }

        $value = (float) $rawScore;
        if ($value <= 1.0) {
            $value *= 100;
        }

        return max(0, min(100, (int) round($value)));
    }

    private function resolveSourcesCount(Event $event): ?int
    {
        $sources = $event->matched_sources;
        if (!is_array($sources)) {
            return null;
        }

        return count($sources);
    }
}

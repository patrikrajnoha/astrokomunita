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
                'label' => 'Nezname',
                'reason' => 'Skore nie je dostupne.',
            ];
        }

        $verifiedScore = (int) config('events.public_confidence.verified_score', 80);
        $partialScore = (int) config('events.public_confidence.partial_score', 60);
        $verifiedMinSources = (int) config('events.public_confidence.verified_min_sources', 2);
        $partialMinSources = (int) config('events.public_confidence.partial_min_sources', 1);
        $safeSourcesCount = $sourcesCount ?? 0;

        if ($score >= $verifiedScore && $safeSourcesCount >= $verifiedMinSources) {
            return $this->makeBadge('verified', 'Overene', $score, $sourcesCount);
        }

        if ($score >= $partialScore && $safeSourcesCount >= $partialMinSources) {
            return $this->makeBadge('partial', 'Ciastocne overene', $score, $sourcesCount);
        }

        return $this->makeBadge('low', 'Nizka dovera', $score, $sourcesCount);
    }

    private function makeBadge(string $level, string $label, int $score, ?int $sourcesCount): array
    {
        $sourcesText = $sourcesCount === null ? 'neznamym poctom zdrojov' : "{$sourcesCount} zdrojmi";

        return [
            'score' => $score,
            'sources_count' => $sourcesCount,
            'level' => $level,
            'label' => $label,
            'reason' => "Skore {$score}/100, potvrdene {$sourcesText}.",
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

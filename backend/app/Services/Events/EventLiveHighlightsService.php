<?php

namespace App\Services\Events;

use App\Services\Sky\SkySpaceWeatherService;

class EventLiveHighlightsService
{
    public function __construct(
        private readonly SkySpaceWeatherService $skySpaceWeatherService
    ) {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function build(float $lat, float $lon, string $tz): array
    {
        $aurora = $this->buildAuroraHighlight($lat, $lon, $tz);

        return array_values(array_filter([
            $aurora,
        ]));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildAuroraHighlight(float $lat, float $lon, string $tz): ?array
    {
        $payload = $this->skySpaceWeatherService->fetchAurora($lat, $lon, $tz);

        if (($payload['available'] ?? false) !== true) {
            return null;
        }

        $score = is_numeric($payload['watch_score'] ?? null)
            ? (int) round((float) $payload['watch_score'])
            : null;
        if ($score === null) {
            return null;
        }

        $minScore = max(0, (int) config('observing.sky.aurora_event_surface_min_score', 15));
        if ($score < $minScore) {
            return null;
        }

        $detailParts = [];
        $corridor = is_numeric($payload['corridor_peak_score'] ?? null)
            ? (int) round((float) $payload['corridor_peak_score'])
            : null;
        $nearest = is_numeric($payload['nearest_score'] ?? null)
            ? (int) round((float) $payload['nearest_score'])
            : null;

        if ($corridor !== null) {
            $detailParts[] = sprintf('Koridor severne: %d/100', $corridor);
        }

        if ($nearest !== null) {
            $detailParts[] = sprintf('Najblizsia bunka: %d/100', $nearest);
        }

        return [
            'key' => 'aurora_watch',
            'kind' => 'live_signal',
            'type' => 'aurora',
            'title' => 'Aurora watch',
            'badge' => 'Zive teraz',
            'status_label' => $this->stringOrNull($payload['watch_label'] ?? null) ?? 'Aurora watch',
            'status_score' => $score,
            'tone' => $this->toneForScore($score),
            'summary' => $this->summaryForScore($score),
            'detail' => $detailParts !== [] ? implode(' | ', $detailParts) : null,
            'forecast_for' => $this->stringOrNull($payload['forecast_for'] ?? null),
            'observed_at' => $this->stringOrNull($payload['observed_at'] ?? null),
            'updated_at' => $this->stringOrNull($payload['updated_at'] ?? null),
            'inference' => $this->stringOrNull($payload['inference'] ?? null),
            'source' => is_array($payload['source'] ?? null) ? $payload['source'] : [],
        ];
    }

    private function toneForScore(int $score): string
    {
        if ($score >= 70) {
            return 'high';
        }

        if ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    private function summaryForScore(int $score): string
    {
        if ($score >= 70) {
            return 'Signal je silny aj nad severnym obzorom.';
        }

        if ($score >= 40) {
            return 'Signal je zvyseny smerom na sever.';
        }

        return 'Slaby signal na severe, oplati sa sledovat severny obzor.';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

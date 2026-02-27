<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Services\Events\PublicConfidenceService;
use Tests\TestCase;

class PublicConfidenceServiceTest extends TestCase
{
    public function test_verified_badge_for_high_score_and_multiple_sources(): void
    {
        $event = new Event([
            'confidence_score' => 0.9,
            'matched_sources' => ['imo', 'astropixels'],
        ]);

        $badge = app(PublicConfidenceService::class)->badgeFor($event);

        $this->assertSame('verified', $badge['level']);
        $this->assertSame('Overené', $badge['label']);
        $this->assertSame('Potvrdené viacerými zdrojmi.', $badge['reason']);
        $this->assertSame(90, $badge['score']);
        $this->assertSame(2, $badge['sources_count']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $badge['reason']);
    }

    public function test_partial_badge_for_medium_score_and_single_source(): void
    {
        $event = new Event([
            'confidence_score' => 0.7,
            'matched_sources' => ['imo'],
        ]);

        $badge = app(PublicConfidenceService::class)->badgeFor($event);

        $this->assertSame('partial', $badge['level']);
        $this->assertSame('Čiastočne overené', $badge['label']);
        $this->assertSame('Potvrdené aspoň jedným zdrojom.', $badge['reason']);
        $this->assertSame(70, $badge['score']);
        $this->assertSame(1, $badge['sources_count']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $badge['reason']);
    }

    public function test_low_badge_for_low_score(): void
    {
        $event = new Event([
            'confidence_score' => 0.4,
            'matched_sources' => ['imo'],
        ]);

        $badge = app(PublicConfidenceService::class)->badgeFor($event);

        $this->assertSame('low', $badge['level']);
        $this->assertSame('Nízka dôvera', $badge['label']);
        $this->assertSame('Nedostatočné potvrdenie z viacerých zdrojov.', $badge['reason']);
        $this->assertSame(40, $badge['score']);
        $this->assertSame(1, $badge['sources_count']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $badge['reason']);
    }

    public function test_unknown_badge_for_null_score(): void
    {
        $event = new Event([
            'confidence_score' => null,
            'matched_sources' => ['imo'],
        ]);

        $badge = app(PublicConfidenceService::class)->badgeFor($event);

        $this->assertSame('unknown', $badge['level']);
        $this->assertSame('Neznáme', $badge['label']);
        $this->assertSame('Nie sú dostupné údaje o dôveryhodnosti.', $badge['reason']);
        $this->assertNull($badge['score']);
        $this->assertSame(1, $badge['sources_count']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $badge['reason']);
    }
}

<?php

namespace Tests\Unit\Translation;

use App\Services\Translation\AstronomyPhraseNormalizer;
use Tests\TestCase;

class AstronomyPhraseNormalizerTest extends TestCase
{
    public function test_it_normalizes_known_bad_conjunction_variants(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $result = $normalizer->normalize('Jupiter v konflikte so slnkom', 'sk');
        $this->assertSame('Jupiter v konjunkcii so Slnkom', $result);

        $accented = $normalizer->normalize("Merk\u{00FA}r na vrchole", 'sk');
        $this->assertSame("Merk\u{00FA}r v hornej konjunkcii", $accented);
    }

    public function test_it_normalizes_mixed_quarter_moon_variants(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $phase = $normalizer->normalize("11 10:39 POSLEDN\u{0130} QUARTER MOON", 'sk');
        $this->assertSame("11 10:39 Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $phase);

        $first = $normalizer->normalize("PRV\u{0130} KVARTN\u{0130} MOON", 'sk');
        $this->assertSame("Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $first);
    }

    public function test_it_uses_original_title_fallback_when_mixed_language_remains(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $resolution = $normalizer->normalizeTitleWithFallback(
            translatedTitle: 'Saturn with Slnko',
            originalTitle: 'Saturn in Conjunction with Sun',
            language: 'sk'
        );

        $this->assertTrue((bool) $resolution['used_fallback']);
        $this->assertSame('deterministic_original', $resolution['reason']);
        $this->assertSame('Saturn v konjunkcii so Slnkom', $resolution['title']);
    }

    public function test_it_uses_deterministic_fallback_when_title_has_encoding_artifacts(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $resolution = $normalizer->normalizeTitleWithFallback(
            translatedTitle: 'Ortu? pri odrazeferora',
            originalTitle: 'Mercury at Inferior Conjunction',
            language: 'sk'
        );

        $this->assertTrue((bool) $resolution['used_fallback']);
        $this->assertSame('deterministic_original', $resolution['reason']);
        $this->assertSame("Merk\u{00FA}r v dolnej konjunkcii", $resolution['title']);
    }

    public function test_it_uses_deterministic_fallback_when_planet_does_not_match_original(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $resolution = $normalizer->normalizeTitleWithFallback(
            translatedTitle: "Ortu\u{0165} v konjunkcii so Slnkom",
            originalTitle: 'Mars in Conjunction with Sun',
            language: 'sk'
        );

        $this->assertTrue((bool) $resolution['used_fallback']);
        $this->assertSame('deterministic_original_mismatch', $resolution['reason']);
        $this->assertSame('Mars v konjunkcii so Slnkom', $resolution['title']);
    }
}

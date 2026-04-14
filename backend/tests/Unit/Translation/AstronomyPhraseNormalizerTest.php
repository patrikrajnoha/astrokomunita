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

        $badSkLastQuarter = $normalizer->normalize("Posledn\u{00FD} \u{0161}tvr\u{0165} Mesiac", 'sk');
        $this->assertSame("Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $badSkLastQuarter);

        $badSkFirstQuarter = $normalizer->normalize("Prv\u{00FD} \u{0161}tvr\u{0165} Mesiac", 'sk');
        $this->assertSame("Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $badSkFirstQuarter);
    }

    public function test_it_normalizes_directional_planet_titles_from_english_and_bad_slovak_shorthand(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $english = $normalizer->normalize('Mercury 3.4 N of Mars', 'sk');
        $this->assertSame("Merk\u{00FA}r 3,4\u{00B0} severne od Marsu", $english);

        $badSk = $normalizer->normalize("Ortu\u{0165} 3,4\u{00B0} s. \u{0161}. Marsu", 'sk');
        $this->assertSame("Merk\u{00FA}r 3,4\u{00B0} severne od Marsu", $badSk);
    }

    public function test_it_replaces_wrong_planet_name_ortut_with_merkur_in_aphelion_titles(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $genitive = $normalizer->normalize("Ortu\u{0165} pri af\u{00E9}liu", 'sk');
        $this->assertSame("Merk\u{00FA}r pri af\u{00E9}liu", $genitive);

        $locative = $normalizer->normalize("Ortu\u{0165} pri af\u{00E9}li\u{00F3}ne", 'sk');
        $this->assertSame("Merk\u{00FA}r pri af\u{00E9}li\u{00F3}ne", $locative);
    }

    public function test_it_localizes_pleiades_in_directional_titles(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $subject = $normalizer->normalize('Pleiades 1.0 S of Moon', 'sk');
        $this->assertSame("Plej\u{00E1}dy 1,0\u{00B0} ju\u{017E}ne od Mesiaca", $subject);

        $target = $normalizer->normalize('Venus 3.4 S of Pleiades', 'sk');
        $this->assertSame("Venu\u{0161}a 3,4\u{00B0} ju\u{017E}ne od Plej\u{00E1}d", $target);
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

    public function test_it_normalizes_perihelion_aphelion_and_opposition_titles(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $perihelion = $normalizer->normalize('Mars at Perihelion: 1.38126 AU', 'sk');
        $this->assertSame("Mars v perih\u{00E9}liu: 1.38126 AU", $perihelion);

        $aphelion = $normalizer->normalize('Earth at Aphelion: 1.01664 AU', 'sk');
        $this->assertSame("Zem v af\u{00E9}liu: 1.01664 AU", $aphelion);

        $opposition = $normalizer->normalize('Jupiter at Opposition', 'sk');
        $this->assertSame("Jupiter v opoz\u{00ED}cii", $opposition);
    }

    public function test_it_uses_deterministic_fallback_for_perihelion_planet_mismatch(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $resolution = $normalizer->normalizeTitleWithFallback(
            translatedTitle: "Jupiter v perih\u{00E9}liu: 1.38126 AU",
            originalTitle: 'Mars at Perihelion: 1.38126 AU',
            language: 'sk'
        );

        $this->assertTrue((bool) $resolution['used_fallback']);
        $this->assertSame('deterministic_original_mismatch', $resolution['reason']);
        $this->assertSame("Mars v perih\u{00E9}liu: 1.38126 AU", $resolution['title']);
    }

    public function test_it_normalizes_common_english_description_fragments(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $result = $normalizer->normalize(
            'Lunar phase from USNO API for Bratislava. Visibility from Slovakia depends on local weather. N Taurid Meteor Shower.',
            'sk'
        );

        $this->assertStringContainsString("f\u{00E1}za Mesiaca", $result);
        $this->assertStringContainsString("vidite\u{013E}nos\u{0165} zo Slovenska", $result);
        $this->assertStringContainsString("z\u{00E1}vis\u{00ED} od miestneho po\u{010D}asia", $result);
        $this->assertMatchesRegularExpression('/meteorick(?:y|\x{00FD})\s+roj/iu', $result);
    }

    public function test_it_normalizes_meteor_shower_title_variants_from_astropixels(): void
    {
        $normalizer = app(AstronomyPhraseNormalizer::class);

        $result = $normalizer->normalize('Geminid Meteor Sprcha', 'sk');
        $this->assertSame('Meteorický roj Geminid', $result);

        $second = $normalizer->normalize("Eta-Aquarid meteorick\u{00E1} sprcha", 'sk');
        $this->assertSame('Meteorický roj Eta-Akvarid', $second);

        $third = $normalizer->normalize('Leonids (LEO) meteor shower', 'sk');
        $this->assertSame('Meteorický roj Leonid', $third);
    }
}

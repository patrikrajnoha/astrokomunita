<?php

namespace Tests\Unit;

use App\Models\TranslationCacheEntry;
use App\Models\TranslationLog;
use App\Models\TranslationOverride;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_overrides_and_reuses_cache_for_identical_input(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', true);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.libretranslate.translate_path', '/translate');

        TranslationOverride::query()->create([
            'source_term' => 'meteor shower',
            'target_term' => 'meteoricky roj',
            'language_from' => 'en',
            'language_to' => 'sk',
            'is_case_sensitive' => false,
        ]);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $service = app(TranslationService::class);

        $first = $service->translate('Strong meteor shower tonight', 'en', 'sk');
        $second = $service->translate('Strong meteor shower tonight', 'en', 'sk');

        $this->assertStringContainsString('meteoricky roj', $first->translatedText);
        $this->assertSame($first->translatedText, $second->translatedText);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains((string) data_get($request->data(), 'q'), 'meteoricky roj');
        });

        $this->assertSame(1, TranslationCacheEntry::query()->count());
        $this->assertDatabaseHas('translation_logs', ['status' => 'success']);
        $this->assertDatabaseHas('translation_logs', ['status' => 'cached']);
    }

    public function test_it_falls_back_to_argos_when_primary_provider_fails(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', 'argos_microservice');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.argos_microservice.base_url', 'http://argos.test');

        Http::fake([
            'http://libre.test/*' => Http::response(['error' => 'down'], 500),
            'http://argos.test/*' => Http::response([
                'translated' => 'Fallback preklad',
            ], 200),
        ]);

        $result = app(TranslationService::class)->translate('Opposition tonight', 'en', 'sk');

        $this->assertSame('Fallback preklad', $result->translatedText);
        $this->assertSame('argos_microservice', $result->provider);

        $this->assertTrue(TranslationLog::query()
            ->where('provider', 'libretranslate')
            ->where('status', 'failed')
            ->exists());

        $this->assertTrue(TranslationLog::query()
            ->where('provider', 'argos_microservice')
            ->where('status', 'success')
            ->exists());
    }

    public function test_it_applies_grammar_corrections_when_enabled(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', true);
        config()->set('translation.grammar.provider', 'languagetool');
        config()->set('translation.grammar.languages', ['sk']);
        config()->set('translation.grammar.languagetool.base_url', 'http://lt.test');
        config()->set('translation.grammar.languagetool.check_path', '/v2/check');
        config()->set('translation.grammar.languagetool.language', 'sk-SK');

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'Prvy STVRT Mesiac',
            ], 200),
            'http://lt.test/*' => Http::response([
                'matches' => [
                    [
                        'offset' => 5,
                        'length' => 5,
                        'replacements' => [
                            ['value' => 'stvrt'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(TranslationService::class)->translate('First Quarter Moon', 'en', 'sk');

        $this->assertSame("Prv\u{00E1} stvrt Mesiaca", $result->translatedText);
    }
    public function test_it_fails_open_when_grammar_check_service_is_unavailable(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', true);
        config()->set('translation.grammar.provider', 'languagetool');
        config()->set('translation.grammar.languages', ['sk']);
        config()->set('translation.grammar.languagetool.base_url', 'http://lt.test');
        config()->set('translation.grammar.languagetool.check_path', '/v2/check');

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'Prvy STVRT Mesiac',
            ], 200),
            'http://lt.test/*' => Http::response(['error' => 'service down'], 503),
        ]);

        $result = app(TranslationService::class)->translate('First Quarter Moon', 'en', 'sk');

        $this->assertSame("Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $result->translatedText);
    }
    public function test_it_uses_word_boundaries_for_overrides_to_avoid_inside_word_replacements(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        TranslationOverride::query()->create([
            'source_term' => 'sun',
            'target_term' => 'slnko',
            'language_from' => 'en',
            'language_to' => 'sk',
            'is_case_sensitive' => false,
        ]);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $result = app(TranslationService::class)->translate('sunset and sun rise', 'en', 'sk');

        $this->assertSame('sunset and slnko rise', $result->translatedText);
    }

    public function test_it_applies_target_language_post_corrections(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        TranslationOverride::query()->create([
            'source_term' => 'Mesiak',
            'target_term' => 'Mesiac',
            'language_from' => 'sk',
            'language_to' => 'sk',
            'is_case_sensitive' => false,
        ]);

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'Mesiak pri vzostupny uzol',
            ], 200),
        ]);

        $result = app(TranslationService::class)->translate('Moon at Ascending Node', 'en', 'sk');

        $this->assertSame('Mesiac pri vzostupny uzol', $result->translatedText);
    }

    public function test_it_applies_hardcoded_lunar_quarter_phrase_fixes(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'prva tlac mesiaca',
            ], 200),
        ]);

        $result = app(TranslationService::class)->translate('First Quarter Moon', 'en', 'sk');

        $this->assertSame(
            mb_strtolower("prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca", 'UTF-8'),
            mb_strtolower($result->translatedText, 'UTF-8')
        );
    }
    public function test_it_applies_hardcoded_conjunction_phrase_fixes(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $result = app(TranslationService::class)->translate('Saturn in Conjunction with Sun', 'en', 'sk');
        $this->assertSame('Saturn v konjunkcii so Slnkom', $result->translatedText);

        $second = app(TranslationService::class)->translate('Venusa v Inferior Conjunction', 'en', 'sk');
        $this->assertSame('Venusa v dolnej konjunkcii', $second->translatedText);
    }

    public function test_it_applies_hardcoded_perihelion_aphelion_and_opposition_phrase_fixes(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $perihelion = app(TranslationService::class)->translate('Mars at Perihelion: 1.38126 AU', 'en', 'sk');
        $this->assertSame("Mars v perih\u{00E9}liu: 1.38126 AU", $perihelion->translatedText);

        $aphelion = app(TranslationService::class)->translate('Earth at Aphelion: 1.01664 AU', 'en', 'sk');
        $this->assertSame("Zem v af\u{00E9}liu: 1.01664 AU", $aphelion->translatedText);

        $opposition = app(TranslationService::class)->translate('Jupiter at Opposition', 'en', 'sk');
        $this->assertSame("Jupiter v opoz\u{00ED}cii", $opposition->translatedText);
    }

    public function test_it_normalizes_known_bad_provider_phrase_variants(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                $text = (string) data_get($request->data(), 'q');

                return Http::response([
                    'translatedText' => match ($text) {
                        'Jupiter in Conjunction with Sun' => 'Jupiter v konflikte so slnkom',
                        'Mercury at Superior Conjunction' => 'Merkur na vrchole',
                        'Mercury at Inferior Conjunction' => 'Merkur pri odrazeferora',
                        default => $text,
                    },
                ], 200);
            },
        ]);

        $first = app(TranslationService::class)->translate('Jupiter in Conjunction with Sun', 'en', 'sk');
        $this->assertSame('Jupiter v konjunkcii so Slnkom', $first->translatedText);

        $second = app(TranslationService::class)->translate('Mercury at Superior Conjunction', 'en', 'sk');
        $this->assertSame('Merkur v hornej konjunkcii', $second->translatedText);

        $third = app(TranslationService::class)->translate('Mercury at Inferior Conjunction', 'en', 'sk');
        $this->assertSame('Merkur v dolnej konjunkcii', $third->translatedText);
    }

    public function test_it_normalizes_directional_planet_titles_after_translation(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                $text = (string) data_get($request->data(), 'q');

                return Http::response([
                    'translatedText' => match ($text) {
                        'Mercury 3.4 N of Mars' => "Ortu\u{0165} 3,4\u{00B0} s. \u{0161}. Marsu",
                        default => $text,
                    },
                ], 200);
            },
        ]);

        $result = app(TranslationService::class)->translate('Mercury 3.4 N of Mars', 'en', 'sk');

        $this->assertSame("Merk\u{00FA}r 3,4\u{00B0} severne od Marsu", $result->translatedText);
    }

    public function test_it_applies_phrase_fixes_even_when_translation_is_loaded_from_cache(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', true);
        config()->set('translation.cache_key_version', 'v7');
        config()->set('translation.grammar.enabled', false);
        config()->set('translation.grammar.provider', 'none');
        config()->set('translation.grammar.languagetool.language', '');

        $text = 'Venus at Inferior Conjunction';
        $from = 'en';
        $to = 'sk';
        $cacheKey = hash('sha256', implode('|', [
            $text,
            $from,
            $to,
            'v7',
            'grammar-off',
            'none',
            '',
        ]));

        TranslationCacheEntry::query()->create([
            'cache_key' => $cacheKey,
            'original_text_hash' => hash('sha256', $text),
            'language_from' => $from,
            'language_to' => $to,
            'provider' => 'libretranslate',
            'translated_text' => 'Venusa v Inferior Conjunction',
            'last_used_at' => now(),
        ]);

        $result = app(TranslationService::class)->translate($text, $from, $to);

        $this->assertSame('Venusa v dolnej konjunkcii', $result->translatedText);
        $this->assertTrue($result->fromCache);
    }

}

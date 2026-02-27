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
                'translatedText' => 'Prvý STVRT Mesiac',
            ], 200),
            'http://lt.test/*' => Http::response([
                'matches' => [
                    [
                        'offset' => 5,
                        'length' => 5,
                        'replacements' => [
                            ['value' => 'štvrť'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(TranslationService::class)->translate('First Quarter Moon', 'en', 'sk');

        $this->assertSame('Prvý štvrť Mesiac', $result->translatedText);
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
                'translatedText' => 'Prvý STVRT Mesiac',
            ], 200),
            'http://lt.test/*' => Http::response(['error' => 'service down'], 503),
        ]);

        $result = app(TranslationService::class)->translate('First Quarter Moon', 'en', 'sk');

        $this->assertSame('Prvý STVRT Mesiac', $result->translatedText);
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

        $this->assertSame('prvá štvrť Mesiaca', $result->translatedText);
    }

}

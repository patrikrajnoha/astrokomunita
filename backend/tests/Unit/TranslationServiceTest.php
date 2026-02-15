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
}

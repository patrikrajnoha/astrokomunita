<?php

namespace Tests\Feature;

use App\Jobs\TranslateRssItemJob;
use App\Models\RssItem;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\AI\OllamaRefinementService;
use App\Services\AstroBotNasaService;
use App\Services\TranslationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TranslateRssItemJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureTranslation(): void
    {
        config()->set('translation.default_provider', 'argos_microservice');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.argos_microservice.base_url', 'http://translation.test');
        config()->set('translation.argos_microservice.internal_token', 'token');
    }

    private function runJob(int $itemId): void
    {
        (new TranslateRssItemJob($itemId))->handle(
            app(TranslationService::class),
            app(OllamaRefinementService::class)
        );
    }

    public function test_upsert_dispatches_translation_job_after_commit(): void
    {
        Queue::fake();

        DB::transaction(function (): void {
            app(AstroBotNasaService::class)->upsertItems([
                [
                    'guid' => 'translate-guid-1',
                    'link' => 'https://www.nasa.gov/news/translate-guid-1',
                    'title' => 'Title',
                    'summary' => 'Summary',
                    'published_at' => Carbon::parse('2026-02-12 10:00:00'),
                    'fingerprint' => sha1('translate-guid-1'),
                ],
            ]);
        });

        Queue::assertPushed(TranslateRssItemJob::class, 1);
    }

    public function test_job_marks_item_done_and_saves_translations(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
                'meta' => [
                    'engine' => 'argos',
                    'from' => 'en',
                    'to' => 'sk',
                    'took_ms' => 10,
                ],
            ], 200),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-ok',
            'url' => 'https://www.nasa.gov/news/job-ok',
            'dedupe_hash' => sha1('job-ok'),
            'stable_key' => sha1('job-ok'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $this->runJob($item->id);

        $item->refresh();
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
        $this->assertSame('Prelozene', $item->translated_title);
        $this->assertSame('Prelozene', $item->translated_summary);
        $this->assertNotNull($item->translated_at);
    }

    public function test_job_marks_item_failed_when_translation_service_errors(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-fail',
            'url' => 'https://www.nasa.gov/news/job-fail',
            'dedupe_hash' => sha1('job-fail'),
            'stable_key' => sha1('job-fail'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        try {
            $this->runJob($item->id);
            $this->fail('Expected translation job to throw on HTTP 500.');
        } catch (\Throwable) {
            $item->refresh();
            $this->assertSame(RssItem::TRANSLATION_FAILED, $item->translation_status);
            $this->assertSame('argos_http_500', $item->translation_error);
            $this->assertSame('Original title', $item->translated_title);
            $this->assertSame('Original summary', $item->translated_summary);
            $this->assertNotNull($item->translated_at);
        }
    }

    public function test_job_is_idempotent_when_translation_is_done(): void
    {
        Http::fake();

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-idempotent',
            'url' => 'https://www.nasa.gov/news/job-idempotent',
            'dedupe_hash' => sha1('job-idempotent'),
            'stable_key' => sha1('job-idempotent'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'original_title' => 'Original title',
            'original_summary' => 'Original summary',
            'translated_title' => 'Uz prelozene',
            'translated_summary' => 'Uz prelozene summary',
            'translation_status' => RssItem::TRANSLATION_DONE,
            'translated_at' => now(),
            'status' => RssItem::STATUS_DRAFT,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $this->runJob($item->id);

        Http::assertNothingSent();
        $item->refresh();
        $this->assertSame('Uz prelozene', $item->translated_title);
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
    }

    public function test_job_replaces_translated_fields_when_refinement_enabled(): void
    {
        $this->configureTranslation();
        config()->set('ai.ollama_refinement_enabled', true);

        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => '{"refined_title":"Maximum meteorického roja Perzeidy","refined_description":"Meteorický roj je pravidelný úkaz na oblohe.\n\nMaximum je najlepšie sledovať v druhej polovici noci mimo svetelného smogu."}',
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);
        $this->app->instance(OllamaClient::class, $client);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
            ], 200),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-refine-ok',
            'url' => 'https://www.nasa.gov/news/job-refine-ok',
            'dedupe_hash' => sha1('job-refine-ok'),
            'stable_key' => sha1('job-refine-ok'),
            'title' => 'Peak of the Perseids meteor shower',
            'summary' => 'A yearly meteor shower that can be seen at night with the naked eye.',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $this->runJob($item->id);

        $item->refresh();
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
        $this->assertSame('Maximum meteorického roja Perzeidy', $item->translated_title);
        $this->assertStringContainsString('Meteorický roj je pravidelný úkaz na oblohe.', (string) $item->translated_summary);
    }

    public function test_job_keeps_translated_fields_when_refinement_throws_exception(): void
    {
        $this->configureTranslation();
        config()->set('ai.ollama_refinement_enabled', true);

        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willThrowException(new OllamaClientException('Ollama down.', 'ollama_connection_error'));
        $this->app->instance(OllamaClient::class, $client);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
            ], 200),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-refine-fallback',
            'url' => 'https://www.nasa.gov/news/job-refine-fallback',
            'dedupe_hash' => sha1('job-refine-fallback'),
            'stable_key' => sha1('job-refine-fallback'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $this->runJob($item->id);

        $item->refresh();
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
        $this->assertSame('Prelozene', $item->translated_title);
        $this->assertSame('Prelozene', $item->translated_summary);
        $this->assertNull($item->translation_error);
    }

    public function test_job_does_not_call_refinement_when_disabled(): void
    {
        $this->configureTranslation();
        config()->set('ai.ollama_refinement_enabled', false);

        $refiner = $this->createMock(OllamaRefinementService::class);
        $refiner->expects($this->never())->method('refine');
        $this->app->instance(OllamaRefinementService::class, $refiner);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
            ], 200),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-refine-disabled',
            'url' => 'https://www.nasa.gov/news/job-refine-disabled',
            'dedupe_hash' => sha1('job-refine-disabled'),
            'stable_key' => sha1('job-refine-disabled'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $this->runJob($item->id);

        $item->refresh();
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
        $this->assertSame('Prelozene', $item->translated_title);
        $this->assertSame('Prelozene', $item->translated_summary);
    }
}

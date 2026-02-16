<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\AI\OllamaRefinementService;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslateEventCandidateJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureTranslation(): void
    {
        config()->set('translation.default_provider', 'argos_microservice');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.argos_microservice.base_url', 'http://translation.test');
        config()->set('translation.argos_microservice.internal_token', 'token');
    }

    private function runJob(int $candidateId): void
    {
        (new TranslateEventCandidateJob($candidateId))->handle(
            app(TranslationService::class),
            app(OllamaRefinementService::class)
        );
    }

    public function test_job_marks_event_candidate_done_and_saves_translations(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
            ], 200),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'source-1',
            'external_id' => 'source-1',
            'stable_key' => 'source-1',
            'source_hash' => hash('sha256', 'source-1'),
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
        $this->assertSame('Prelozene', $candidate->translated_description);
        $this->assertNotNull($candidate->translated_at);
    }

    public function test_job_marks_event_candidate_failed_when_translation_errors(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'source-2',
            'external_id' => 'source-2',
            'stable_key' => 'source-2',
            'source_hash' => hash('sha256', 'source-2'),
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]);

        try {
            $this->runJob($candidate->id);
            $this->fail('Expected translation job to throw on HTTP 500.');
        } catch (\Throwable) {
            $candidate->refresh();
            $this->assertSame(EventCandidate::TRANSLATION_FAILED, $candidate->translation_status);
            $this->assertSame('argos_http_500', $candidate->translation_error);
            $this->assertSame('Original title', $candidate->translated_title);
            $this->assertSame('Original description', $candidate->translated_description);
            $this->assertNotNull($candidate->translated_at);
        }
    }

    public function test_job_replaces_translated_fields_when_refinement_enabled(): void
    {
        $this->configureTranslation();
        config()->set('ai.ollama_refinement_enabled', true);

        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => '{"refined_title":"Maximum meteorického roja Perzeidy","refined_description":"Ide o periodický meteorický roj.\n\nMaximum nastáva v noci. Pozorovanie je možné voľným okom mimo mesta."}',
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

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'source-3',
            'external_id' => 'source-3',
            'stable_key' => 'source-3',
            'source_hash' => hash('sha256', 'source-3'),
            'title' => 'Peak of the Perseids meteor shower',
            'description' => 'This shower peaks overnight and can be observed with the naked eye.',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Maximum meteorického roja Perzeidy', $candidate->translated_title);
        $this->assertStringContainsString('Ide o periodický meteorický roj.', (string) $candidate->translated_description);
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

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'source-4',
            'external_id' => 'source-4',
            'stable_key' => 'source-4',
            'source_hash' => hash('sha256', 'source-4'),
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
        $this->assertSame('Prelozene', $candidate->translated_description);
        $this->assertNull($candidate->translation_error);
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

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'source-5',
            'external_id' => 'source-5',
            'stable_key' => 'source-5',
            'source_hash' => hash('sha256', 'source-5'),
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Short',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
        $this->assertSame('Prelozene', $candidate->translated_description);
    }
}

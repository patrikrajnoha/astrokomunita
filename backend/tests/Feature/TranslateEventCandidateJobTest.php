<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
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
        config()->set('events.refine_descriptions_with_ollama', false);
        config()->set('events.description_template_min_length', 40);
        config()->set('ai.ollama_retry_attempts', 1);
        config()->set('ai.ollama_refinement_enabled', false);
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

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid template fallback.',
            'type' => 'other',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
        $this->assertSame('Prelozene', $candidate->translated_description);
        $this->assertSame('Prelozene', $candidate->description);
        $this->assertNotNull($candidate->short);
        $this->assertNotNull($candidate->translated_at);
    }

    public function test_job_marks_event_candidate_failed_when_translation_errors(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
        ]);

        try {
            $this->runJob($candidate->id);
            $this->fail('Expected translation job to throw on HTTP 500.');
        } catch (\Throwable) {
            $candidate->refresh();
            $this->assertSame(EventCandidate::TRANSLATION_FAILED, $candidate->translation_status);
            $this->assertSame('argos_http_500', $candidate->translation_error);
            $this->assertSame('Original title', $candidate->translated_title);
            $this->assertNotNull($candidate->translated_description);
            $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->translated_description);
            $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->description);
            $this->assertNotNull($candidate->translated_at);
        }
    }

    public function test_job_generates_template_description_when_original_description_missing(): void
    {
        $this->configureTranslation();

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Perzeidy',
            ], 200),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => null,
            'type' => 'meteor_shower',
            'raw_payload' => '{"zhr":120}',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertStringContainsString('Meteoricky roj', (string) $candidate->translated_description);
        $this->assertStringContainsString('120 meteorov za hodinu', (string) $candidate->translated_description);
        $this->assertStringContainsString('Maximum meteorickeho roja', (string) $candidate->short);
    }

    public function test_job_uses_refined_description_when_refinement_enabled(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);

        $refiner = $this->createMock(OllamaRefinementService::class);
        $refiner->expects($this->once())
            ->method('refine')
            ->willReturn([
                'refined_title' => 'Maximum roja Perzeidy',
                'refined_description' => 'Perzeidy vrcholia priblizne 12.08.2026. Pozorovanie je najlepsie mimo mesta.',
                'used_fallback' => false,
            ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Perzeidy',
            ], 200),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => null,
            'type' => 'meteor_shower',
            'max_at' => '2026-08-12 00:00:00',
            'start_at' => '2026-08-12 00:00:00',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Maximum roja Perzeidy', $candidate->translated_title);
        $this->assertSame('Perzeidy vrcholia priblizne 12.08.2026. Pozorovanie je najlepsie mimo mesta.', $candidate->translated_description);
    }

    public function test_job_fail_open_keeps_template_when_refinement_throws_exception(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);

        $refiner = $this->createMock(OllamaRefinementService::class);
        $refiner->expects($this->once())
            ->method('refine')
            ->willThrowException(new \RuntimeException('Ollama timeout'));
        $this->app->instance(OllamaRefinementService::class, $refiner);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Perzeidy',
            ], 200),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => null,
            'type' => 'meteor_shower',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertStringContainsString('Meteoricky roj', (string) $candidate->translated_description);
        $this->assertNull($candidate->translation_error);
    }

    public function test_job_does_not_call_refinement_when_disabled(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', false);

        $refiner = $this->createMock(OllamaRefinementService::class);
        $refiner->expects($this->never())->method('refine');
        $this->app->instance(OllamaRefinementService::class, $refiner);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
            ], 200),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid template fallback.',
            'type' => 'other',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function makeCandidate(array $overrides = []): EventCandidate
    {
        return EventCandidate::query()->create(array_merge([
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
        ], $overrides));
    }
}

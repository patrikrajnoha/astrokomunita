<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaRefinementService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Translation\AstronomyPhraseNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateEventCandidateJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureTranslation(): void
    {
        config()->set('events.refine_descriptions_with_ollama', false);
        config()->set('events.description_template_min_length', 40);
        config()->set('ai.ollama_retry_attempts', 1);
        config()->set('ai.ollama_refinement_enabled', false);
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');
        config()->set('bots.translation.timeout_sec', 8);
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function fakeTranslationResult(?string $translatedTitle, ?string $translatedContent, array $meta = []): void
    {
        $this->app->instance(
            BotTranslationServiceInterface::class,
            new TranslateEventCandidateJobTranslationStub(
                translatedTitle: $translatedTitle,
                translatedContent: $translatedContent,
                meta: $meta,
            )
        );
    }

    private function runJob(int $candidateId): void
    {
        (new TranslateEventCandidateJob($candidateId))->handle(
            app(BotTranslationServiceInterface::class),
            app(OllamaRefinementService::class),
            app(AstronomyPhraseNormalizer::class),
        );
    }

    public function test_job_marks_event_candidate_done_and_saves_translations(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult('Prelozene', 'Prelozene');

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
        $this->app->instance(
            BotTranslationServiceInterface::class,
            new TranslateEventCandidateJobTranslationStub(
                exception: new \App\Services\Bots\Exceptions\BotTranslationException('boom'),
            )
        );

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
            $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
            $this->assertNull($candidate->translation_error);
            $this->assertSame('Original title', $candidate->translated_title);
            $this->assertNotNull($candidate->translated_description);
            $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->translated_description);
            $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->description);
            $this->assertNotNull($candidate->translated_at);
        }
    }

    public function test_job_normalizes_meteor_shower_title_in_translation_error_fallback(): void
    {
        $this->configureTranslation();
        $this->app->instance(
            BotTranslationServiceInterface::class,
            new TranslateEventCandidateJobTranslationStub(
                exception: new \App\Services\Bots\Exceptions\BotTranslationException('boom'),
            )
        );

        $candidate = $this->makeCandidate([
            'title' => 'Perseid Meteor Sprcha',
            'description' => null,
            'type' => 'meteor_shower',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertNull($candidate->translation_error);
        $this->assertSame('Meteoricky roj Perzeid', $candidate->translated_title);
        $this->assertNotNull($candidate->translated_description);
        $this->assertStringContainsString('Meteoricky roj Perzeid ma maximum', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('Meteoricky roj Meteoricky roj', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('Meteor Sprcha', (string) $candidate->translated_description);
    }

    public function test_job_generates_template_description_when_original_description_missing(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult('Perzeidy', null);

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

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'Maximum roja Perzeidy',
            'refined_description' => 'Perzeidy vrcholia priblizne 12.08.2026. Pozorovanie je najlepsie mimo mesta.',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult('Perzeidy', null);

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
        $this->assertSame(1, $refiner->calls);
    }

    public function test_job_fail_open_keeps_template_when_refinement_throws_exception(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);

        $refiner = new TranslateEventCandidateJobRefinementStub(
            exception: new \RuntimeException('Ollama timeout')
        );
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult('Perzeidy', null);

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
        $this->assertSame(1, $refiner->calls);
    }

    public function test_job_does_not_call_refinement_when_disabled(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', false);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'unused',
            'refined_description' => 'unused',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult('Prelozene', 'Prelozene');

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid template fallback.',
            'type' => 'other',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene', $candidate->translated_title);
        $this->assertSame(0, $refiner->calls);
    }

    public function test_job_normalizes_conjunction_phrases_to_slovak(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Saturn in Conjunction with Slnko',
            'Astronomicka udalost Saturn in Conjunction with Sun nastane 25.03.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Saturn in Conjunction with Sun',
            'description' => 'Saturn in Conjunction with Sun occurs on 25.03.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Saturn v konjunkcii so Slnkom', $candidate->translated_title);
        $this->assertStringContainsString('v konjunkcii so Slnkom', (string) $candidate->translated_description);
    }

    public function test_job_normalizes_inferior_conjunction_variant_to_slovak(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Venuša v Inferior Conjunction',
            'Venuša v Inferior Conjunction nastane 24.10.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Venus at Inferior Conjunction',
            'description' => 'Venus at Inferior Conjunction occurs on 24.10.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Venuša v dolnej konjunkcii', $candidate->translated_title);
        $this->assertStringContainsString('v dolnej konjunkcii', (string) $candidate->translated_description);
    }

    public function test_job_normalizes_known_bad_provider_phrase_variants(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Jupiter v konflikte so slnkom',
            'Jupiter v konflikte so slnkom nastane 29.10.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Jupiter in Conjunction with Sun',
            'description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Jupiter v konjunkcii so Slnkom', $candidate->translated_title);
        $this->assertStringContainsString('v konjunkcii so Slnkom', (string) $candidate->translated_description);
    }

    public function test_job_uses_quality_gate_fallback_for_mixed_language_title(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Saturn with Slnko',
            'Saturn with Slnko nastane 25.03.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Saturn in Conjunction with Sun',
            'description' => 'Saturn in Conjunction with Sun occurs on 25.03.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Saturn v konjunkcii so Slnkom', $candidate->translated_title);
        $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('with Slnko', (string) $candidate->translated_description);
    }

    public function test_job_normalizes_peak_and_odrazeferora_variants(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Merkur na vrchole',
            'Merkur pri odrazeferora nastane 12.07.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Mercury at Inferior Conjunction',
            'description' => 'Mercury at Inferior Conjunction occurs on 12.07.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Merk\u{00FA}r v dolnej konjunkcii", $candidate->translated_title);
        $this->assertStringContainsString('v dolnej konjunkcii', (string) $candidate->translated_description);
    }

    public function test_job_uses_deterministic_title_when_translation_has_encoding_artifacts(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Ortu? pri odrazeferora',
            'Ortu? pri odrazeferora nastane 12.07.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Mercury at Inferior Conjunction',
            'description' => 'Mercury at Inferior Conjunction occurs on 12.07.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Merk\u{00FA}r v dolnej konjunkcii", $candidate->translated_title);
        $this->assertStringContainsString('v dolnej konjunkcii', (string) $candidate->translated_description);
    }

    public function test_job_forces_template_when_translation_quality_flags_are_severe(): void
    {
        $this->configureTranslation();
        config()->set('events.translation.quality_gate.force_template_on_severe_flags', true);
        config()->set('events.translation.quality_gate.severe_flags', [
            'empty_result',
            'identical',
            'too_short',
            'too_much_en',
            'contains_en_connectors',
            'encoding_artifacts',
        ]);

        $this->fakeTranslationResult(
            'Saturn with Slnko',
            'Saturn with Slnko occurs on 25.03.2026.',
            ['quality_flags' => ['too_much_en', 'contains_en_connectors']]
        );

        $candidate = $this->makeCandidate([
            'title' => 'Saturn in Conjunction with Sun',
            'description' => 'Saturn in Conjunction with Sun occurs on 25.03.2026 with sufficient content for non-template flow.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertStringContainsString('Astronomicka udalost', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('occurs on', (string) $candidate->translated_description);
    }

    public function test_job_normalizes_mixed_quarter_moon_variants(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            "POSLEDN\u{0130} KVARTN\u{0130} MOON",
            "11 10:39 POSLEDN\u{0130} QUARTER MOON"
        );

        $candidate = $this->makeCandidate([
            'title' => 'LAST QUARTER MOON',
            'description' => 'LAST QUARTER MOON occurs on 11.03.2026.',
            'type' => 'moon_phase',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $candidate->translated_title);
        $this->assertStringContainsString("Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", (string) $candidate->translated_description);
    }

    public function test_job_fixes_wrong_slovak_moon_phase_grammar(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            "Posledn\u{00FD} \u{0161}tvr\u{0165} Mesiac",
            "Posledn\u{00FD} \u{0161}tvr\u{0165} Mesiac nastane 30.12.2026."
        );

        $candidate = $this->makeCandidate([
            'title' => 'LAST QUARTER MOON',
            'description' => 'LAST QUARTER MOON occurs on 30.12.2026.',
            'type' => 'observation_window',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", $candidate->translated_title);
        $this->assertStringContainsString("Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca", (string) $candidate->translated_description);
    }

    public function test_job_replaces_english_source_short_with_translated_short(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Leonidy',
            'Leonidy su meteoricky roj s maximom priblizne 17.11.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Leonids (LEO)',
            'description' => 'The Leonids are best known for producing meteor storms.',
            'short' => 'The Leonids are best known for producing meteor storms.',
            'type' => 'meteor_shower',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Leonidy', $candidate->translated_title);
        $this->assertStringContainsString('Leonidy', (string) $candidate->short);
        $this->assertStringNotContainsString('best known', (string) $candidate->short);
    }

    public function test_job_normalizes_astropixels_meteor_sprcha_title_to_slovak(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Geminid Meteor Sprcha',
            'Maximum roja Geminid Meteor Sprcha je priblizne 14.12.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Geminid Meteor Shower',
            'description' => 'Geminid Meteor Shower peaks around 14.12.2026.',
            'short' => 'Geminid Meteor Sprcha',
            'type' => 'meteor_shower',
            'source_name' => 'astropixels',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Meteoricky roj Geminid', $candidate->translated_title);
        $this->assertStringContainsString('meteoricky', mb_strtolower((string) $candidate->short, 'UTF-8'));
    }

    public function test_job_falls_back_to_template_when_translated_description_stays_english(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Leonidy',
            'The Leonids are best known for producing meteor storms in the years of 1833, 1866, and 1966.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Leonids (LEO)',
            'description' => 'The Leonids are best known for producing meteor storms in the years of 1833, 1866, and 1966.',
            'type' => 'meteor_shower',
            'source_name' => 'imo',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertStringContainsString('Meteoricky roj', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('best known', (string) $candidate->translated_description);
    }

    /**
     * @param  array<string,mixed>  $overrides
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

final class TranslateEventCandidateJobTranslationStub implements BotTranslationServiceInterface
{
    public function __construct(
        private readonly ?string $translatedTitle = null,
        private readonly ?string $translatedContent = null,
        private readonly array $meta = [],
        private readonly ?\Throwable $exception = null,
    ) {}

    public function translate(?string $title, ?string $content, string $to = 'sk'): array
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return [
            'translated_title' => $this->translatedTitle,
            'translated_content' => $this->translatedContent,
            'title_translated' => $this->translatedTitle,
            'content_translated' => $this->translatedContent,
            'status' => $this->translatedTitle !== null || $this->translatedContent !== null ? 'done' : 'skipped',
            'meta' => array_merge([
                'provider' => 'test-double',
                'target_lang' => $to,
            ], $this->meta),
        ];
    }
}

final class TranslateEventCandidateJobRefinementStub extends OllamaRefinementService
{
    public int $calls = 0;

    /**
     * @param  array{refined_title?:string,refined_description?:?string,used_fallback?:bool}  $result
     */
    public function __construct(
        private readonly array $result = [],
        private readonly ?\Throwable $exception = null,
    ) {
        parent::__construct(new OllamaClient);
    }

    public function refine(
        string $originalEnglishTitle,
        ?string $originalEnglishDescription,
        string $translatedTitle,
        ?string $translatedDescription
    ): array {
        $this->calls++;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return [
            'refined_title' => $this->result['refined_title'] ?? $translatedTitle,
            'refined_description' => $this->result['refined_description'] ?? $translatedDescription,
            'used_fallback' => (bool) ($this->result['used_fallback'] ?? false),
            'model' => 'stub-model',
            'duration_ms' => 1,
        ];
    }
}

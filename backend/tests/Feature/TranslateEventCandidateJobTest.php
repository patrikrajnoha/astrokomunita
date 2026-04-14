<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\Event;
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

    private function runJob(int $candidateId, ?string $requestedMode = null): void
    {
        (new TranslateEventCandidateJob($candidateId, false, $requestedMode))->handle(
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
        $this->assertSame(EventCandidate::TRANSLATION_MODE_TEMPLATE, $candidate->translation_mode);
        $this->assertNotNull($candidate->translated_description);
        $this->assertNotNull($candidate->short);
        $this->assertNotNull($candidate->translated_at);
    }

    public function test_job_syncs_description_to_published_event_for_approved_candidate(): void
    {
        $this->configureTranslation();
        config()->set('events.translation.refinement.skip_on_template_fallback', false);
        $this->fakeTranslationResult('Prelozene SK', null);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'Prelozene SK',
            'refined_description' => 'AI popis kandidata pre publikovany event.',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $event = Event::query()->create([
            'title' => 'Povodny event title',
            'description' => 'Povodny event popis',
            'short' => 'Povodny short',
            'type' => 'other',
            'start_at' => now(),
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'event-sync-1',
            'source_hash' => hash('sha256', 'event-sync-1'),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid template fallback.',
            'type' => 'other',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
        ]);

        $this->runJob($candidate->id, 'ai');

        $candidate->refresh();
        $event->refresh();

        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('Prelozene SK', $event->title);
        $this->assertSame('AI popis kandidata pre publikovany event.', $event->description);
    }

    public function test_job_does_not_sync_event_when_candidate_is_not_approved(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult('Prelozene SK', 'AI popis kandidata bez schvalenia.');

        $event = Event::query()->create([
            'title' => 'Povodny event title',
            'description' => 'Povodny event popis',
            'short' => 'Povodny short',
            'type' => 'other',
            'start_at' => now(),
            'visibility' => 1,
            'source_name' => 'imo',
            'source_uid' => 'event-sync-2',
            'source_hash' => hash('sha256', 'event-sync-2'),
        ]);

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid template fallback.',
            'type' => 'other',
            'status' => EventCandidate::STATUS_PENDING,
            'published_event_id' => $event->id,
        ]);

        $this->runJob($candidate->id, 'ai');

        $event->refresh();
        $this->assertSame('Povodny event title', $event->title);
        $this->assertSame('Povodny event popis', $event->description);
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
            $this->assertStringContainsString("Astronomick\u{00E1} udalos\u{0165}", (string) $candidate->translated_description);
            $this->assertStringContainsString("Astronomick\u{00E1} udalos\u{0165}", (string) $candidate->description);
            $this->assertNotNull($candidate->translated_at);
        }
    }

    public function test_job_uses_template_when_explicit_ai_translation_fails(): void
    {
        $this->configureTranslation();
        $this->app->instance(
            BotTranslationServiceInterface::class,
            new TranslateEventCandidateJobTranslationStub(
                exception: new \App\Services\Bots\Exceptions\BotTranslationException('boom'),
            )
        );
        // Simulate Ollama also being unavailable so the mode stays template.
        $this->app->instance(OllamaRefinementService::class, new TranslateEventCandidateJobRefinementStub([
            'used_fallback' => true,
        ]));

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description',
            'type' => 'other',
        ]);

        $this->runJob($candidate->id, 'ai');

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame(EventCandidate::TRANSLATION_MODE_TEMPLATE, $candidate->translation_mode);
        $this->assertNotNull($candidate->translated_description);
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
        $this->assertSame('Meteorický roj Perzeid', $candidate->translated_title);
        $this->assertNotNull($candidate->translated_description);
        $this->assertStringContainsString('Perzeid', (string) $candidate->translated_description);
        $this->assertStringNotContainsString('Meteorický roj Meteorický roj', (string) $candidate->translated_description);
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
        $this->assertStringContainsStringIgnoringCase('meteorick', (string) $candidate->translated_description);
        $this->assertStringContainsString('120 meteorov za hodinu', (string) $candidate->translated_description);
        $this->assertStringContainsStringIgnoringCase('meteorick', (string) $candidate->short);
    }

    public function test_job_uses_refined_description_when_refinement_enabled(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);
        config()->set('events.translation.refinement.skip_on_template_fallback', false);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'Maximum roja Perzeidy',
            'refined_description' => 'Perzeidy vrcholia priblizne 12.08.2026. Pozorovanie je najlepsie mimo mesta.',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult(
            'Perzeidy',
            'Perzeidy su meteoricky roj s maximom priblizne 12.08.2026. Pozorovanie je mozne pri dobrych podmienkach.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => 'The Perseids are active each August and are visible under dark skies.',
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

    public function test_job_skips_refinement_when_template_fallback_is_active(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);
        config()->set('events.translation.refinement.skip_on_template_fallback', true);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'unused',
            'refined_description' => 'unused',
            'used_fallback' => false,
        ]);
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
        $this->assertStringContainsStringIgnoringCase('meteorick', (string) $candidate->translated_description);
        $this->assertNull($candidate->translation_error);
        $this->assertSame(0, $refiner->calls);
    }

    public function test_job_allows_refinement_on_template_fallback_for_explicit_ai_mode(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);
        config()->set('events.translation.refinement.skip_on_template_fallback', true);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'Perzeidy AI',
            'refined_description' => 'Perzeidy vrcholia priblizne 12.08.2026. Sleduj oblohu po zotmeni.',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult('Perzeidy', null);

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => null,
            'type' => 'meteor_shower',
        ]);

        $this->runJob($candidate->id, 'ai');

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame(EventCandidate::TRANSLATION_MODE_AI_REFINED, $candidate->translation_mode);
        $this->assertSame('Perzeidy AI', $candidate->translated_title);
        $this->assertSame('Perzeidy vrcholia priblizne 12.08.2026. Sleduj oblohu po zotmeni.', $candidate->translated_description);
        $this->assertSame(1, $refiner->calls);
    }

    public function test_job_forces_template_mode_when_requested(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);

        $refiner = new TranslateEventCandidateJobRefinementStub([
            'refined_title' => 'unused',
            'refined_description' => 'unused',
            'used_fallback' => false,
        ]);
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult(
            'Prelozene',
            'Prelozene s dostatocnym obsahom, aby inak nebol aktivny template fallback.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Original title',
            'description' => 'Original description with enough content to avoid automatic template fallback.',
            'type' => 'other',
        ]);

        $this->runJob($candidate->id, 'template');

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame(EventCandidate::TRANSLATION_MODE_TEMPLATE, $candidate->translation_mode);
        $this->assertStringContainsString("Astronomick\u{00E1} udalos\u{0165}", (string) $candidate->translated_description);
        $this->assertSame(0, $refiner->calls);
    }

    public function test_job_fail_open_keeps_base_text_when_refinement_throws_exception(): void
    {
        $this->configureTranslation();
        config()->set('events.refine_descriptions_with_ollama', true);
        config()->set('events.translation.refinement.skip_on_template_fallback', false);

        $refiner = new TranslateEventCandidateJobRefinementStub(
            exception: new \RuntimeException('Ollama timeout')
        );
        $this->app->instance(OllamaRefinementService::class, $refiner);

        $this->fakeTranslationResult(
            'Perzeidy',
            'Perzeidy su meteoricky roj s maximom priblizne 12.08.2026. Pozorovanie je mozne pri dobrych podmienkach.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Perseids meteor shower',
            'description' => 'The Perseids are active each August and are visible under dark skies.',
            'type' => 'meteor_shower',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertStringContainsStringIgnoringCase('meteorick', (string) $candidate->translated_description);
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

    public function test_job_normalizes_perihelion_title_variants_to_slovak(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Mars at Perihelion: 1.38126 AU',
            'Mars at Perihelion: 1.38126 AU occurs on 26.03.2026.'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Mars at Perihelion: 1.38126 AU',
            'description' => 'Mars at Perihelion: 1.38126 AU occurs on 26.03.2026.',
            'type' => 'planetary_event',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Mars v perih\u{00E9}liu: 1.38126 AU", $candidate->translated_title);
        $this->assertStringContainsString("v perih\u{00E9}liu", (string) $candidate->translated_description);
        $this->assertStringNotContainsString('at Perihelion', (string) $candidate->translated_title);
    }

    public function test_job_localizes_pleiades_directional_title_and_description(): void
    {
        $this->configureTranslation();
        $this->fakeTranslationResult(
            'Pleiades 1.0 S of Moon',
            'Pleiades 1.0 S of Moon'
        );

        $candidate = $this->makeCandidate([
            'title' => 'Pleiades 1.0 S of Moon',
            'description' => 'Pleiades 1.0 S of Moon appears in the source description with enough context to avoid template fallback.',
            'type' => 'observation_window',
            'source_name' => 'astropixels',
        ]);

        $this->runJob($candidate->id);

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame("Plej\u{00E1}dy 1,0\u{00B0} ju\u{017E}ne od Mesiaca", $candidate->translated_title);
        $this->assertStringContainsString("Plej\u{00E1}dy", (string) $candidate->translated_description);
        $this->assertStringContainsString('Mesiac', (string) $candidate->translated_description);
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
        $this->assertStringContainsString('Saturn v konjunkcii so Slnkom', (string) $candidate->translated_description);
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
        $this->assertStringContainsStringIgnoringCase("posledn\u{00E1} \u{0161}tvr\u{0165} mesiaca", (string) $candidate->translated_description);
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
        $this->assertStringContainsStringIgnoringCase("posledn\u{00E1} \u{0161}tvr\u{0165} mesiaca", (string) $candidate->translated_description);
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
        $this->assertStringContainsString('Leonid', (string) $candidate->short);
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
        $this->assertSame('Meteorický roj Geminid', $candidate->translated_title);
        $this->assertStringContainsStringIgnoringCase('meteorick', (string) $candidate->short);
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
     * @param  array{refined_title?:string,refined_description?:?string,used_fallback?:bool,title_used_fallback?:bool,description_used_fallback?:bool}  $result
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
        ?string $translatedDescription,
        bool $forceRun = false,
    ): array {
        $this->calls++;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        $usedFallback = (bool) ($this->result['used_fallback'] ?? false);

        return [
            'refined_title' => $this->result['refined_title'] ?? $translatedTitle,
            'refined_description' => $this->result['refined_description'] ?? $translatedDescription,
            'used_fallback' => $usedFallback,
            'title_used_fallback' => (bool) ($this->result['title_used_fallback'] ?? $usedFallback),
            'description_used_fallback' => (bool) ($this->result['description_used_fallback'] ?? $usedFallback),
            'model' => 'stub-model',
            'duration_ms' => 1,
        ];
    }
}

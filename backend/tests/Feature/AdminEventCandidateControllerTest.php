<?php

namespace Tests\Feature;

use App\Jobs\ProcessEventCandidatePublishRunJob;
use App\Jobs\TranslateEventCandidateJob;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
use App\Models\EventCandidatePublishRun;
use App\Models\EventSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventCandidateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    public function test_admin_can_retranslate_single_candidate(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-1',
            'external_id' => 'imo-candidate-1',
            'stable_key' => 'imo-candidate-1',
            'translation_status' => EventCandidate::TRANSLATION_FAILED,
            'translation_error' => 'provider_down',
        ]));

        $response = $this->postJson("/api/admin/event-candidates/{$candidate->id}/retranslate");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('candidate.translation_status', EventCandidate::TRANSLATION_PENDING)
            ->assertJsonPath('candidate.translation_error', null);

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($candidate): bool {
            return $job->candidateId === (int) $candidate->id && $job->force === true;
        });
    }

    public function test_admin_can_retranslate_single_candidate_in_template_mode(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-template-mode',
            'external_id' => 'imo-candidate-template-mode',
            'stable_key' => 'imo-candidate-template-mode',
            'translation_status' => EventCandidate::TRANSLATION_FAILED,
            'translation_error' => 'provider_down',
        ]));

        $response = $this->postJson("/api/admin/event-candidates/{$candidate->id}/retranslate", [
            'mode' => 'template',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode_applied', 'template')
            ->assertJsonPath('candidate.translation_status', EventCandidate::TRANSLATION_PENDING)
            ->assertJsonPath('candidate.translation_error', null);

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($candidate): bool {
            return $job->candidateId === (int) $candidate->id
                && $job->force === true
                && $job->requestedMode === 'template';
        });
    }

    public function test_admin_approve_single_candidate_uses_template_generation_mode_by_default(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-approve-template-default',
            'external_id' => 'imo-candidate-approve-template-default',
            'stable_key' => 'imo-candidate-approve-template-default',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TRANSLATED,
            'translated_description' => 'Strojovo prelozeny popis',
        ]));

        $response = $this->postJson("/api/admin/event-candidates/{$candidate->id}/approve");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('publish_generation_mode', 'template');

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($candidate): bool {
            return $job->candidateId === (int) $candidate->id
                && $job->force === true
                && $job->requestedMode === 'template';
        });
    }

    public function test_admin_approve_single_candidate_archives_previous_ai_variant_before_template_publish(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-approve-template-archive',
            'external_id' => 'imo-candidate-approve-template-archive',
            'stable_key' => 'imo-candidate-approve-template-archive',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
            'translated_title' => 'AI nadpis',
            'translated_description' => 'AI popis pred prepinanim na sablonu',
            'raw_payload' => '{}',
        ]));

        $this->postJson("/api/admin/event-candidates/{$candidate->id}/approve")
            ->assertOk()
            ->assertJsonPath('publish_generation_mode', 'template');

        $candidate->refresh();
        $payload = json_decode((string) ($candidate->raw_payload ?? '{}'), true);
        $variants = is_array($payload['description_variants'] ?? null) ? $payload['description_variants'] : [];
        $latestVariant = is_array($variants) ? end($variants) : null;

        $this->assertIsArray($latestVariant);
        $this->assertSame(EventCandidate::TRANSLATION_MODE_AI_REFINED, (string) ($latestVariant['mode'] ?? ''));
        $this->assertSame('AI popis pred prepinanim na sablonu', (string) ($latestVariant['description'] ?? ''));
        $this->assertSame('template', (string) ($latestVariant['requested_publish_mode'] ?? ''));
    }

    public function test_admin_can_retranslate_batch_with_template_scope_and_template_mode(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $templateCandidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-template-scope-1',
            'external_id' => 'imo-candidate-template-scope-1',
            'stable_key' => 'imo-candidate-template-scope-1',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            'description' => 'Template-like description',
        ]));

        $nonTemplateCandidate = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-template-scope-2',
            'external_id' => 'imo-candidate-template-scope-2',
            'stable_key' => 'imo-candidate-template-scope-2',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
            'description' => 'AI refined description',
        ]));

        $response = $this->postJson('/api/admin/event-candidates/retranslate-batch', [
            'mode' => 'template',
            'ai_scope' => 'template',
            'limit' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode_applied', 'template')
            ->assertJsonPath('scope_applied', 'template')
            ->assertJsonPath('queued', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('total_selected', 1);

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($templateCandidate): bool {
            return $job->candidateId === (int) $templateCandidate->id
                && $job->force === true
                && $job->requestedMode === 'template';
        });

        Bus::assertNotDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($nonTemplateCandidate): bool {
            return $job->candidateId === (int) $nonTemplateCandidate->id;
        });
    }

    public function test_admin_can_retranslate_batch_with_missing_scope_based_on_translated_description(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $missingTranslatedDescription = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-missing-translation',
            'external_id' => 'imo-candidate-missing-translation',
            'stable_key' => 'imo-candidate-missing-translation',
            'description' => 'Source English description',
            'translated_description' => null,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
        ]));

        $alreadyTranslated = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'imo-candidate-with-translation',
            'external_id' => 'imo-candidate-with-translation',
            'stable_key' => 'imo-candidate-with-translation',
            'description' => 'Prelozeny popis',
            'translated_description' => 'Prelozeny popis',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
        ]));

        $response = $this->postJson('/api/admin/event-candidates/retranslate-batch', [
            'mode' => 'ai',
            'ai_scope' => 'missing',
            'limit' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode_applied', 'ai')
            ->assertJsonPath('scope_applied', 'missing')
            ->assertJsonPath('queued', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('total_selected', 1);

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($missingTranslatedDescription): bool {
            return $job->candidateId === (int) $missingTranslatedDescription->id
                && $job->force === true
                && $job->requestedMode === 'ai';
        });

        Bus::assertNotDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($alreadyTranslated): bool {
            return $job->candidateId === (int) $alreadyTranslated->id;
        });
    }

    public function test_admin_approve_batch_uses_template_generation_mode_by_default(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'approve-batch-default-template',
            'external_id' => 'approve-batch-default-template',
            'stable_key' => 'approve-batch-default-template',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TRANSLATED,
            'translated_description' => 'Strojovo prelozeny popis',
        ]));

        $response = $this->postJson('/api/admin/event-candidates/approve-batch', [
            'status' => EventCandidate::STATUS_PENDING,
            'limit' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('published', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('publish_generation_mode', 'template');

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($candidate): bool {
            return $job->candidateId === (int) $candidate->id
                && $job->force === true
                && $job->requestedMode === 'template';
        });
    }

    public function test_admin_approve_batch_uses_requested_generation_mode(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $candidate = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'approve-batch-ai-mode',
            'external_id' => 'approve-batch-ai-mode',
            'stable_key' => 'approve-batch-ai-mode',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            'translated_description' => 'Sablonovy popis',
        ]));

        $response = $this->postJson('/api/admin/event-candidates/approve-batch', [
            'status' => EventCandidate::STATUS_PENDING,
            'limit' => 1000,
            'mode' => 'ai',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('published', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('publish_generation_mode', 'ai');

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($candidate): bool {
            return $job->candidateId === (int) $candidate->id
                && $job->force === true
                && $job->requestedMode === 'ai';
        });
    }

    public function test_admin_approve_batch_mix_keeps_manual_candidate_without_retranslation(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $manualCandidate = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'approve-batch-mix-manual',
            'external_id' => 'approve-batch-mix-manual',
            'stable_key' => 'approve-batch-mix-manual',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_MANUAL,
            'translated_description' => 'Rucne upraveny popis',
        ]));

        $response = $this->postJson('/api/admin/event-candidates/approve-batch', [
            'status' => EventCandidate::STATUS_PENDING,
            'limit' => 1000,
            'mode' => 'mix',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('published', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('publish_generation_mode', 'mix');

        Bus::assertNotDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job) use ($manualCandidate): bool {
            return $job->candidateId === (int) $manualCandidate->id;
        });
    }

    public function test_admin_can_start_approve_batch_run_and_query_its_status(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        config()->set('queue.default', 'database');

        EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'approve-batch-run-start',
            'external_id' => 'approve-batch-run-start',
            'stable_key' => 'approve-batch-run-start',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            'translated_description' => 'Sablonovy popis',
        ]));

        $startResponse = $this->postJson('/api/admin/event-candidates/approve-batch/start', [
            'status' => EventCandidate::STATUS_PENDING,
            'limit' => 1000,
            'mode' => 'template',
        ]);

        $startResponse->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('run.total_selected', 1)
            ->assertJsonPath('run.status', EventCandidatePublishRun::STATUS_QUEUED)
            ->assertJsonPath('run.publish_generation_mode', 'template');

        $runId = (int) $startResponse->json('run.id');
        $this->assertGreaterThan(0, $runId);

        Bus::assertDispatched(ProcessEventCandidatePublishRunJob::class, function (ProcessEventCandidatePublishRunJob $job) use ($runId): bool {
            return $job->runId === $runId;
        });

        $this->getJson("/api/admin/event-candidates/approve-batch/runs/{$runId}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.id', $runId)
            ->assertJsonPath('run.total_selected', 1);
    }

    public function test_admin_approve_batch_start_returns_done_when_nothing_matches_filter(): void
    {
        $this->actingAsAdmin();
        Bus::fake();

        $response = $this->postJson('/api/admin/event-candidates/approve-batch/start', [
            'status' => EventCandidate::STATUS_APPROVED,
            'limit' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('run.status', EventCandidatePublishRun::STATUS_COMPLETED)
            ->assertJsonPath('run.total_selected', 0)
            ->assertJsonPath('run.is_terminal', true)
            ->assertJsonPath('run.progress_percent', 100);

        Bus::assertNotDispatched(ProcessEventCandidatePublishRunJob::class);
    }

    public function test_admin_can_filter_candidates_by_run_id_using_source_and_created_window(): void
    {
        $this->actingAsAdmin();

        $imoSource = EventSource::query()->create([
            'key' => 'imo',
            'name' => 'IMO',
            'base_url' => 'https://imo.test',
            'is_enabled' => true,
        ]);

        $otherSource = EventSource::query()->create([
            'key' => 'astropixels',
            'name' => 'AstroPixels',
            'base_url' => 'https://astropixels.test',
            'is_enabled' => true,
        ]);

        $run = CrawlRun::query()->create([
            'event_source_id' => $imoSource->id,
            'source_name' => 'imo',
            'source_url' => 'https://imo.test/run',
            'year' => 2026,
            'status' => 'success',
            'started_at' => '2026-04-01 10:00:00',
            'finished_at' => '2026-04-01 10:05:00',
        ]);

        $insideWindow = EventCandidate::query()->create($this->candidatePayload([
            'event_source_id' => $imoSource->id,
            'source_name' => 'imo',
            'source_uid' => 'imo-window',
            'external_id' => 'imo-window',
            'stable_key' => 'imo-window',
        ]));
        $insideWindow->forceFill([
            'created_at' => '2026-04-01 10:03:00',
            'updated_at' => '2026-04-01 10:03:00',
        ])->save();

        $outsideWindow = EventCandidate::query()->create($this->candidatePayload([
            'event_source_id' => $imoSource->id,
            'source_name' => 'imo',
            'source_uid' => 'imo-outside',
            'external_id' => 'imo-outside',
            'stable_key' => 'imo-outside',
        ]));
        $outsideWindow->forceFill([
            'created_at' => '2026-04-01 10:25:00',
            'updated_at' => '2026-04-01 10:25:00',
        ])->save();

        $differentSource = EventCandidate::query()->create($this->candidatePayload([
            'event_source_id' => $otherSource->id,
            'source_name' => 'astropixels',
            'source_uid' => 'ap-window',
            'external_id' => 'ap-window',
            'stable_key' => 'ap-window',
        ]));
        $differentSource->forceFill([
            'created_at' => '2026-04-01 10:04:00',
            'updated_at' => '2026-04-01 10:04:00',
        ])->save();

        $response = $this->getJson("/api/admin/event-candidates?run_id={$run->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $insideWindow->id);
    }

    public function test_admin_can_filter_candidates_by_description_mode_ai(): void
    {
        $this->actingAsAdmin();

        $aiTranslated = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-ai-translated',
            'external_id' => 'description-mode-ai-translated',
            'stable_key' => 'description-mode-ai-translated',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TRANSLATED,
            'translated_description' => 'AI prelozeny popis',
        ]));

        $aiRefined = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-ai-refined',
            'external_id' => 'description-mode-ai-refined',
            'stable_key' => 'description-mode-ai-refined',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
            'translated_description' => 'AI upraveny popis',
        ]));

        EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-template',
            'external_id' => 'description-mode-template',
            'stable_key' => 'description-mode-template',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            'translated_description' => 'Sablonovy popis',
        ]));

        $response = $this->getJson('/api/admin/event-candidates?description_mode=ai');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        sort($returnedIds);

        $expectedIds = [(int) $aiRefined->id, (int) $aiTranslated->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $returnedIds);
    }

    public function test_admin_can_filter_candidates_by_description_mode_ai_refined_only(): void
    {
        $this->actingAsAdmin();

        $aiRefined = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-ai-refined-only',
            'external_id' => 'description-mode-ai-refined-only',
            'stable_key' => 'description-mode-ai-refined-only',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
            'translated_description' => 'AI upraveny popis',
        ]));

        EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-translated-only',
            'external_id' => 'description-mode-translated-only',
            'stable_key' => 'description-mode-translated-only',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TRANSLATED,
            'translated_description' => 'Strojovo prelozeny popis',
        ]));

        $response = $this->getJson('/api/admin/event-candidates?description_mode=ai_refined');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', (int) $aiRefined->id);
    }

    public function test_admin_can_filter_candidates_by_description_mode_translated_only(): void
    {
        $this->actingAsAdmin();

        EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-ai-refined-for-translated-filter',
            'external_id' => 'description-mode-ai-refined-for-translated-filter',
            'stable_key' => 'description-mode-ai-refined-for-translated-filter',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_AI_REFINED,
            'translated_description' => 'AI upraveny popis',
        ]));

        $translated = EventCandidate::query()->create($this->candidatePayload([
            'source_uid' => 'description-mode-translated-for-filter',
            'external_id' => 'description-mode-translated-for-filter',
            'stable_key' => 'description-mode-translated-for-filter',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_TRANSLATED,
            'translated_description' => 'Strojovo prelozeny popis',
        ]));

        $response = $this->getJson('/api/admin/event-candidates?description_mode=translated');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', (int) $translated->id);
    }

    public function test_admin_can_preview_pending_duplicate_groups(): void
    {
        $this->actingAsAdmin();

        $ids = $this->seedDuplicateCandidateGroup();

        $response = $this->getJson('/api/admin/event-candidates/duplicates/preview?limit_groups=10&per_group=3');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('summary.group_count', 1);
        $response->assertJsonPath('summary.duplicate_candidates', 2);
        $response->assertJsonPath('groups.0.count', 3);
        $response->assertJsonPath('groups.0.duplicates.0.id', $ids['duplicate_first']);
        $response->assertJsonPath('groups.0.duplicates.1.id', $ids['duplicate_second']);
        $response->assertJsonPath('groups.0.keeper.id', $ids['keeper']);
    }

    public function test_admin_can_merge_pending_duplicate_groups(): void
    {
        $this->actingAsAdmin();

        $ids = $this->seedDuplicateCandidateGroup();

        $response = $this->postJson('/api/admin/event-candidates/duplicates/merge', [
            'limit_groups' => 10,
            'dry_run' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('summary.group_count', 1);
        $response->assertJsonPath('summary.merged_candidates', 2);
        $response->assertJsonPath('groups.0.keeper_id', $ids['keeper']);
        $response->assertJsonPath('groups.0.duplicate_ids.0', $ids['duplicate_first']);
        $response->assertJsonPath('groups.0.duplicate_ids.1', $ids['duplicate_second']);

        $this->assertDatabaseHas('event_candidates', [
            'id' => $ids['duplicate_second'],
            'status' => EventCandidate::STATUS_DUPLICATE,
            'reject_reason' => 'auto_duplicate_merge',
        ]);
        $this->assertDatabaseHas('event_candidates', [
            'id' => $ids['duplicate_first'],
            'status' => EventCandidate::STATUS_DUPLICATE,
            'reject_reason' => 'auto_duplicate_merge',
        ]);
        $this->assertDatabaseHas('event_candidates', [
            'id' => $ids['keeper'],
            'status' => EventCandidate::STATUS_PENDING,
        ]);
    }

    /**
     * @return array{keeper:int,duplicate_first:int,duplicate_second:int}
     */
    private function seedDuplicateCandidateGroup(): array
    {
        $first = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'astropixels',
            'source_uid' => 'dup-1',
            'external_id' => 'dup-1',
            'stable_key' => 'dup-1',
            'canonical_key' => 'meteor shower|2026-04-22|lyrids',
            'confidence_score' => 0.70,
            'matched_sources' => ['astropixels'],
            'title' => 'Lyrids',
            'start_at' => '2026-04-22 20:00:00',
            'max_at' => '2026-04-22 20:00:00',
        ]));

        $second = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'imo',
            'source_uid' => 'dup-2',
            'external_id' => 'dup-2',
            'stable_key' => 'dup-2',
            'canonical_key' => 'meteor shower|2026-04-22|lyrids',
            'confidence_score' => 0.90,
            'matched_sources' => ['imo'],
            'title' => 'Lyrids meteor shower',
            'start_at' => '2026-04-22 20:00:00',
            'max_at' => '2026-04-22 20:00:00',
        ]));

        $third = EventCandidate::query()->create($this->candidatePayload([
            'source_name' => 'nasa_wts',
            'source_uid' => 'dup-3',
            'external_id' => 'dup-3',
            'stable_key' => 'dup-3',
            'canonical_key' => 'meteor shower|2026-04-22|lyrids',
            'confidence_score' => 1.00,
            'matched_sources' => ['astropixels', 'imo', 'nasa_wts'],
            'title' => 'Lyrids (LEO)',
            'start_at' => '2026-04-22 20:00:00',
            'max_at' => '2026-04-22 20:00:00',
        ]));

        return [
            'keeper' => (int) $third->id,
            'duplicate_first' => (int) $second->id,
            'duplicate_second' => (int) $first->id,
        ];
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function candidatePayload(array $overrides = []): array
    {
        return array_merge([
            'source_name' => 'imo',
            'source_url' => 'https://imo.test/event',
            'source_uid' => 'candidate-uid',
            'external_id' => 'candidate-uid',
            'stable_key' => 'candidate-uid',
            'source_hash' => hash('sha256', uniqid('candidate-', true)),
            'title' => 'Candidate title',
            'translated_title' => null,
            'raw_type' => 'meteor_shower',
            'type' => 'meteor_shower',
            'max_at' => '2026-04-01 20:00:00',
            'start_at' => '2026-04-01 20:00:00',
            'end_at' => null,
            'short' => 'Short',
            'description' => 'Description',
            'translated_description' => null,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'translation_error' => null,
            'translated_at' => null,
            'status' => EventCandidate::STATUS_PENDING,
            'raw_payload' => '{}',
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
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
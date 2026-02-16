<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
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

        (new TranslateEventCandidateJob($candidate->id))->handle(app(TranslationService::class));

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
            (new TranslateEventCandidateJob($candidate->id))->handle(app(TranslationService::class));
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
}

<?php

namespace Tests\Feature;

use App\Enums\EventSource as EventSourceEnum;
use App\Jobs\TranslateEventCandidateJob;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventSource;
use App\Models\User;
use App\Services\AI\OllamaRefinementService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Translation\AstronomyPhraseNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImoHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_run_imo_translate_template_and_approve_creates_event_with_matching_fields(): void
    {
        config()->set('translation.default_provider', 'argos_microservice');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.argos_microservice.base_url', 'http://translation.test');
        config()->set('translation.argos_microservice.internal_token', 'token');
        config()->set('events.refine_descriptions_with_ollama', false);
        config()->set('events.description_template_min_length', 40);

        EventSource::query()->create([
            'key' => EventSourceEnum::IMO->value,
            'name' => EventSourceEnum::IMO->label(),
            'base_url' => 'https://www.imo.net/resources/calendar/',
            'is_enabled' => true,
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        Http::fake([
            'https://www.imo.net/resources/calendar/*' => Http::response($html, 200),
            'http://translation.test/*' => Http::response(['translated' => 'Prelozene'], 200),
        ]);

        $runResponse = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => ['imo'],
            'year' => 2026,
        ]);

        $runResponse->assertOk();
        $runResponse->assertJsonPath('results.0.status', 'success');
        $runResponse->assertJsonPath('results.0.created_candidates_count', 2);

        $candidate = EventCandidate::query()
            ->where('source_name', 'imo')
            ->orderBy('id')
            ->firstOrFail();

        $candidate->update([
            'description' => null,
            'original_description' => null,
        ]);

        (new TranslateEventCandidateJob((int) $candidate->id))->handle(
            app(BotTranslationServiceInterface::class),
            app(OllamaRefinementService::class),
            app(AstronomyPhraseNormalizer::class)
        );

        $candidate->refresh();
        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertNotNull($candidate->translated_description);
        $this->assertStringContainsString('meteorický roj', (string) $candidate->translated_description);

        $approveResponse = $this->postJson("/api/admin/event-candidates/{$candidate->id}/approve");
        $approveResponse->assertOk();

        $eventId = (int) $approveResponse->json('published_event_id');
        $event = Event::query()->findOrFail($eventId);

        $this->assertNotNull($event->canonical_key);
        $this->assertSame('0.70', (string) $event->confidence_score);
        $this->assertSame(['imo'], $event->matched_sources);
    }
}

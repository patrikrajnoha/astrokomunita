<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepairEventTranslationArtifactsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_suspicious_candidate_and_published_event_titles(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $event = Event::query()->create([
            'title' => 'Jupiter v konflikte so slnkom',
            'type' => 'planetary_event',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'source_name' => 'astropixels',
            'source_uid' => 'event-1',
            'source_hash' => hash('sha256', 'event-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-1',
            'external_id' => 'candidate-1',
            'stable_key' => 'candidate-1',
            'source_hash' => hash('sha256', 'candidate-1'),
            'title' => 'Jupiter in Conjunction with Sun',
            'original_title' => 'Jupiter in Conjunction with Sun',
            'translated_title' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'original_description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'translated_description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'type' => 'planetary_event',
            'max_at' => now(),
            'start_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->artisan('events:repair-translation-artifacts')
            ->expectsOutputToContain('Suspicious approved candidates found: 1')
            ->assertExitCode(0);

        $candidate->refresh();
        $event->refresh();

        $this->assertSame('Jupiter v konjunkcii so Slnkom', $candidate->translated_title);
        $this->assertSame('Jupiter v konjunkcii so Slnkom', $event->title);
    }
}

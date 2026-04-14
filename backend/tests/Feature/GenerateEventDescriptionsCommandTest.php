<?php

namespace Tests\Feature;

use App\Jobs\GenerateEventDescriptionJob;
use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\Events\EventInsightsCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateEventDescriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Command-level tests focus on generation/guard behavior, not routing policy.
        config()->set('events.ai.description_routing.enabled', false);
    }

    private function createEvent(string $sourceUid, string $title = 'Prva stvrt Mesiaca'): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => $sourceUid,
            'source_hash' => hash('sha256', $sourceUid),
        ]);
    }

    /**
     * @return array<int,Event>
     */
    private function createEvents(int $count): array
    {
        $events = [];
        for ($index = 1; $index <= $count; $index++) {
            $events[] = $this->createEvent(
                sourceUid: 'evt-desc-' . $index,
                title: 'Astronomicky jav #' . $index
            );
        }

        return $events;
    }

    public function test_command_generates_template_descriptions_and_updates_events(): void
    {
        config()->set('events.ai.description_mode', 'template');

        $event = $this->createEvent('evt-desc-template');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=template')
            ->assertExitCode(0);

        $event->refresh();

        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);
        $this->assertStringContainsString('mesiac', mb_strtolower((string) $event->description));
        $this->assertStringContainsString('24. 02. 2026', (string) $event->description);
    }

    public function test_concurrency_flag_dispatches_expected_number_of_jobs(): void
    {
        Queue::fake();

        $this->createEvents(4);

        $this->artisan('events:generate-descriptions --mode=template --limit=3 --concurrency=3')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateEventDescriptionJob::class, 3);
        Queue::assertPushed(
            GenerateEventDescriptionJob::class,
            static fn (GenerateEventDescriptionJob $job): bool => $job->concurrency === 3
        );
    }

    public function test_command_ollama_mode_generates_descriptions(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('events.ai.model', 'mistral');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => '{"description":"Ide o astronomicky jav, ktory pomaha pochopit pohyb telies na oblohe. Oplati sa ho sledovat vecer, pretoze je dobry na bezne pozorovanie. Cas viditelnosti zavisi od polohy pozorovatela.","short":"Astronomicky jav vhodny na vecerne pozorovanie."}',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-ollama', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertStringContainsString('astronomicky jav', mb_strtolower((string) $event->description));
    }

    public function test_command_dry_run_does_not_persist_changes(): void
    {
        $event = $this->createEvent('evt-desc-dry-run');

        $this->artisan('events:generate-descriptions --force --dry-run --limit=1 --mode=template')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNull($event->description);
        $this->assertNull($event->short);
    }

    public function test_humanized_pilot_valid_strict_json_is_persisted(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Ide o zaujimavy astronomicky jav viditelny v uvedenom termine.',
                    'short' => 'Zaujimavy jav na sledovanie.',
                    'why_interesting' => 'Pomaha lepsie pochopit dynamiku oblohy.',
                    'how_to_observe' => 'Pozorujte z tmaveho miesta s nerusenym vyhladom.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-valid', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertSame('Ide o zaujimavy astronomicky jav viditelny v uvedenom termine.', $event->description);
        $this->assertSame('Zaujimavy jav na sledovanie.', $event->short);
    }

    public function test_humanized_insights_cache_uses_configured_ttl_setting(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('events.ai.insights_cache_ttl_seconds', 123);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Ide o zaujimavy astronomicky jav viditelny v uvedenom termine.',
                    'short' => 'Zaujimavy jav na sledovanie.',
                    'why_interesting' => 'Pomaha lepsie pochopit dynamiku oblohy.',
                    'how_to_observe' => 'Pozorujte z tmaveho miesta s nerusenym vyhladom.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-insights-ttl', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $insightsCache = app(EventInsightsCacheService::class);
        $cacheKey = $insightsCache->key((int) $event->id);
        $cached = Cache::get($cacheKey);

        $this->assertSame(123, $insightsCache->ttlSeconds());
        $this->assertIsArray($cached);
        $this->assertNotEmpty((string) ($cached['factual_hash'] ?? ''));
    }

    public function test_humanized_pilot_invalid_json_falls_back_without_job_failure(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => 'Nie je to JSON.',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-invalid', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);
        $this->assertStringContainsString('mesiac', mb_strtolower((string) $event->description));

        $run = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->generated);
        $this->assertSame(0, $run->failed);
    }

    public function test_humanized_pilot_accepts_json_wrapped_in_text_and_code_fence(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        $wrappedResponse = <<<TEXT
Tu je vysledok:
```json
{"description":"Ukaz je lahko pochopitelny a zaujimavy pre bezneho pozorovatela.","short":"Jednoduchy prehlad javu.","why_interesting":"Pomaha sledovat pohyb telies na oblohe.","how_to_observe":"Vyberte tmavsie miesto a doprajte ociam adaptaciu."}
```
Dakujem.
TEXT;

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => $wrappedResponse,
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-wrapped', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertSame('Ukaz je lahko pochopitelny a zaujimavy pre bezneho pozorovatela.', $event->description);
        $this->assertSame('Jednoduchy prehlad javu.', $event->short);
    }

    public function test_humanized_pilot_does_not_fallback_when_optional_insight_is_mildly_over_limit(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        $overlongWhyInteresting = implode(' ', array_fill(
            0,
            8,
            'Je to zaujimave aj pre beznych pozorovatelov, pretoze ide o dobre viditelny jav.'
        ));

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Ide o zaujimavy astronomicky jav, ktory sa da pozorovat aj bez specialnej techniky.',
                    'short' => 'Jav vhodny na bezne pozorovanie.',
                    'why_interesting' => $overlongWhyInteresting,
                    'how_to_observe' => 'Vyberte tmavsie miesto a nechajte si par minut na adaptaciu zraku.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-long-optional', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertSame(
            'Ide o zaujimavy astronomicky jav, ktory sa da pozorovat aj bez specialnej techniky.',
            $event->description
        );
        $this->assertSame('Jav vhodny na bezne pozorovanie.', $event->short);

        $run = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('ollama_humanized', data_get($run->meta, 'last_event_status'));
    }

    public function test_humanized_fallback_log_contains_raw_output_reason_and_fallback_payload(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        Log::spy();

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => 'Nie je to JSON.',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-log-fallback', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);

        Log::shouldHaveReceived('warning')
            ->withArgs(static function (string $message, array $context): bool {
                if ($message !== 'Event description generation JSON guard fallback.') {
                    return false;
                }

                $fallbackDescription = (string) data_get($context, 'fallback_output.description', '');
                $rawExcerpt = (string) ($context['raw_output_excerpt'] ?? '');
                $errorCodes = (array) ($context['error_codes'] ?? []);

                return $rawExcerpt !== ''
                    && str_contains($rawExcerpt, 'Nie je to JSON')
                    && $fallbackDescription !== ''
                    && in_array('json_object_not_found', $errorCodes, true);
            })
            ->once();
    }

    public function test_humanized_pilot_factual_drift_triggers_fallback(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Mesiac je vo vzdialenosti 370000 km od Zeme.',
                    'short' => 'Mesiac vo vzdialenosti 370000 km.',
                    'why_interesting' => 'Rozdielna vzdialenost je pekne viditelna.',
                    'how_to_observe' => 'Sledujte ukaz za dobreho pocasia.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-drift', 'Mesiac v perigeu: 363000 km');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertStringNotContainsString('370000 km', (string) $event->description);
        $this->assertStringContainsString('363', (string) $event->description);
        $this->assertStringContainsString('km', strtolower((string) $event->description));
    }

    public function test_humanized_pilot_obvious_anglicism_triggers_fallback(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Tento jav sa da conductovat aj bez techniky.',
                    'short' => 'Jav vhodny na conductovanie.',
                    'why_interesting' => 'Pomoze lepsie pochopit pohyb telies.',
                    'how_to_observe' => 'Staci tmave miesto.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-anglicism', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);
        $this->assertStringNotContainsString('conductov', mb_strtolower((string) $event->description));
        $this->assertStringContainsString('mesiac', mb_strtolower((string) $event->description));
    }

    public function test_humanized_pilot_respects_custom_forbidden_substring_policy(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('events_ai_policy.safety.forbidden_substrings', [
            'hviezdne metro',
        ]);

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Tento jav sledujte cez hviezdne metro pre lepsi zazitok.',
                    'short' => 'Jav vhodny na hviezdne metro.',
                    'why_interesting' => 'Je to zaujimavy jav.',
                    'how_to_observe' => 'Staci tmave miesto.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-custom-policy', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $normalizedDescription = mb_strtolower((string) $event->description);
        $this->assertStringNotContainsString('hviezdne metro', $normalizedDescription);
        $this->assertStringContainsString('mesiac', $normalizedDescription);
    }

    public function test_humanized_pilot_respects_custom_forbidden_regex_policy(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('events_ai_policy.safety.forbidden_regex', [
            '/\bhviezdne\s+metro\b/iu',
        ]);

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Tento jav sledujte cez hviezdne metro pre lepsi zazitok.',
                    'short' => 'Jav vhodny na hviezdne metro.',
                    'why_interesting' => 'Je to zaujimavy jav.',
                    'how_to_observe' => 'Staci tmave miesto.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-custom-regex-policy', 'First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $normalizedDescription = mb_strtolower((string) $event->description);
        $this->assertStringNotContainsString('hviezdne metro', $normalizedDescription);
        $this->assertStringContainsString('mesiac', $normalizedDescription);
    }

    public function test_humanized_pilot_unexpected_celestial_term_triggers_fallback(): void
    {
        config()->set('events.ai.humanized_pilot_enabled', true);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [],
            ], 200),
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => json_encode([
                    'description' => 'Misia je prepojena s planetou Uranus a jeho pozorovanim.',
                    'short' => 'Misia suvisi s Uranus.',
                    'why_interesting' => 'Spaja sa s planetou Uranus.',
                    'how_to_observe' => 'Sledujte planetu Uranus na nocnej oblohe.',
                ], JSON_UNESCAPED_UNICODE),
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('evt-desc-humanized-celestial', 'Artemis II mission');
        $event->update([
            'type' => 'mission',
            'source_name' => 'manual',
        ]);

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $normalizedDescription = mb_strtolower((string) $event->description);
        $this->assertStringContainsString('artemis', $normalizedDescription);
        $this->assertStringNotContainsString('uranus', $normalizedDescription);
    }

    public function test_ollama_down_with_fallback_base_completes_in_template_mode(): void
    {
        $events = $this->createEvents(3);
        config()->set('ai.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/*' => Http::failedConnection(),
        ]);

        $this->artisan('events:generate-descriptions --force --mode=ollama --fallback=base')
            ->assertExitCode(0);

        foreach ($events as $event) {
            $event->refresh();
            $this->assertNotNull($event->description);
            $this->assertNotNull($event->short);
        }

        $run = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('ollama', $run->requested_mode);
        $this->assertSame('template', $run->effective_mode);
        $this->assertSame(3, $run->generated);
        $this->assertSame(0, $run->failed);
        $this->assertSame('completed', $run->status);
    }

    public function test_preflight_detects_missing_model_and_falls_back_to_template_when_requested(): void
    {
        $event = $this->createEvent('evt-desc-missing-model', 'First Quarter Moon');
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('events.ai.model', 'mistral:latest');

        Http::fake([
            'http://ollama.test/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest'],
                ],
            ], 200),
        ]);

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama --fallback=base')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);

        $run = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('ollama', $run->requested_mode);
        $this->assertSame('template', $run->effective_mode);
    }

    public function test_mid_run_ollama_failures_do_not_abort_batch_and_return_exit_code_2(): void
    {
        $events = $this->createEvents(3);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama_retry_attempts', 3);

        $generateCalls = 0;
        Http::fake(function (Request $request) use (&$generateCalls) {
            if (str_contains($request->url(), '/api/tags')) {
                return Http::response(['models' => []], 200);
            }

            if (str_contains($request->url(), '/api/generate')) {
                $generateCalls++;

                if (in_array($generateCalls, [2, 3, 4], true)) {
                    return Http::failedConnection();
                }

                return Http::response([
                    'model' => 'mistral',
                    'response' => '{"description":"Ide o astronomicky jav, ktory pomaha pochopit pohyb telies na oblohe. Oplati sa ho sledovat vecer, pretoze je dobry na bezne pozorovanie. Cas viditelnosti zavisi od polohy pozorovatela.","short":"Astronomicky jav vhodny na vecerne pozorovanie."}',
                    'done' => true,
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->artisan('events:generate-descriptions --force --mode=ollama --fallback=skip')
            ->assertExitCode(2);

        $events[0]->refresh();
        $events[1]->refresh();
        $events[2]->refresh();

        $this->assertNotNull($events[0]->description);
        $this->assertNull($events[1]->description);
        $this->assertNotNull($events[2]->description);

        $run = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(3, $run->processed);
        $this->assertSame(2, $run->generated);
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, $run->skipped);
        $this->assertSame('completed_with_failures', $run->status);
    }

    public function test_resume_continues_from_partial_run_without_regenerating_existing_events(): void
    {
        $events = $this->createEvents(5);

        $this->artisan('events:generate-descriptions --mode=template --limit=2')
            ->assertExitCode(0);

        $firstRun = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('partial', $firstRun->status);
        $this->assertSame(2, $firstRun->processed);
        $this->assertSame(2, $firstRun->generated);

        $events[0]->refresh();
        $events[1]->refresh();
        $firstUpdatedAt = $events[0]->updated_at;
        $secondUpdatedAt = $events[1]->updated_at;

        sleep(1);

        $this->artisan('events:generate-descriptions --mode=template --resume')
            ->assertExitCode(0);

        foreach ($events as $event) {
            $event->refresh();
            $this->assertNotNull($event->description);
            $this->assertNotNull($event->short);
        }

        $this->assertSame($firstUpdatedAt?->toIso8601String(), $events[0]->updated_at?->toIso8601String());
        $this->assertSame($secondUpdatedAt?->toIso8601String(), $events[1]->updated_at?->toIso8601String());
        $this->assertSame(1, DescriptionGenerationRun::query()->count());

        $finalRun = DescriptionGenerationRun::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $finalRun->status);
        $this->assertSame(5, $finalRun->processed);
        $this->assertSame(5, $finalRun->generated);
        $this->assertSame(max(array_map(static fn (Event $event): int => (int) $event->id, $events)), (int) $finalRun->last_event_id);
    }

    public function test_no_event_is_regenerated_without_force(): void
    {
        $alreadyGenerated = $this->createEvent('evt-existing', 'Already generated');
        $alreadyGenerated->update([
            'description' => 'Predgenerovaný popis udalosti.',
            'short' => 'Predgenerovane kratke zhrnutie.',
        ]);
        $alreadyGenerated->refresh();
        $existingUpdatedAt = $alreadyGenerated->updated_at?->toIso8601String();

        $missing = $this->createEvent('evt-missing', 'Needs generation');

        sleep(1);

        $this->artisan('events:generate-descriptions --mode=template')
            ->assertExitCode(0);

        $alreadyGenerated->refresh();
        $missing->refresh();

        $this->assertSame($existingUpdatedAt, $alreadyGenerated->updated_at?->toIso8601String());
        $this->assertNotNull($missing->description);
        $this->assertNotNull($missing->short);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\GenerateEventDescriptionJob;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use App\Services\Admin\AiLastRunStore;
use App\Services\AI\OllamaClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ai_config_endpoint_is_protected(): void
    {
        $this->getJson('/api/admin/ai/config')
            ->assertStatus(401);
    }

    public function test_all_admin_ai_routes_use_auth_admin_and_throttle_middleware(): void
    {
        $routes = [
            ['GET', '/api/admin/ai/config'],
            ['GET', '/api/admin/ai/policy'],
            ['PATCH', '/api/admin/ai/policy'],
            ['POST', '/api/admin/events/1/ai/generate-description'],
            ['POST', '/api/admin/blog-posts/1/ai/suggest-tags'],
        ];

        foreach ($routes as [$method, $uri]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middleware, $uri . ' missing auth:sanctum');
            $this->assertContains('admin', $middleware, $uri . ' missing admin');
            $this->assertContains('throttle:admin-ai', $middleware, $uri . ' missing throttle:admin-ai');
        }
    }

    public function test_admin_ai_config_endpoint_is_runtime_rate_limited(): void
    {
        config()->set('admin.ai_rate_limit_per_minute', 10);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $ip = '10.0.0.77';
        RateLimiter::clear('admin-ai|' . $admin->id . '|' . $ip);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/admin/ai/config')
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/admin/ai/config')
            ->assertStatus(429);
    }

    public function test_generate_description_endpoint_returns_accepted_and_does_not_leak_prompt_content(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $event = Event::query()->create([
            'title' => 'First Quarter Moon',
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'evt-admin-ai-1',
            'source_hash' => hash('sha256', 'evt-admin-ai-1'),
        ]);

        $response = $this->postJson('/api/admin/events/' . $event->id . '/ai/generate-description', [
            'sync' => false,
        ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonStructure([
                'status',
                'job_id',
                'last_run' => [
                    'feature_name',
                    'status',
                    'latency_ms',
                    'retry_count',
                    'entity_id',
                    'event_id',
                    'updated_at',
                ],
            ]);

        Queue::assertPushed(
            GenerateEventDescriptionJob::class,
            static fn (GenerateEventDescriptionJob $job): bool => $job->eventId === (int) $event->id
        );

        $payload = $response->json();
        $lastRun = (array) data_get($payload, 'last_run', []);

        $this->assertArrayNotHasKey('prompt', (array) $payload);
        $this->assertArrayNotHasKey('raw_text', (array) $payload);
        $this->assertArrayNotHasKey('prompt', $lastRun);
        $this->assertArrayNotHasKey('raw_text', $lastRun);
        $this->assertArrayNotHasKey('meta', $lastRun);
    }

    public function test_generate_description_dry_run_returns_preview_without_persisting_event(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $event = Event::query()->create([
            'title' => 'Dry run event',
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-04-11 20:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-04-11 20:00:00', 'UTC'),
            'short' => 'Povodny short',
            'description' => 'Povodny opis',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'evt-admin-ai-dry-run',
            'source_hash' => hash('sha256', 'evt-admin-ai-dry-run'),
        ]);

        $response = $this->postJson('/api/admin/events/' . $event->id . '/ai/generate-description', [
            'sync' => true,
            'dry_run' => true,
            'mode' => 'template',
            'fallback' => 'skip',
            'force' => false,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('job_id', null)
            ->assertJsonPath('data.event_id', (int) $event->id)
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.fallback_used', true)
            ->assertJsonPath('last_run.feature_name', 'event_description_generate')
            ->assertJsonPath('last_run.status', 'fallback');

        $description = trim((string) $response->json('data.description'));
        $short = trim((string) $response->json('data.short'));
        $this->assertNotSame('', $description);
        $this->assertNotSame('', $short);

        $event->refresh();
        $this->assertSame('Povodny opis', (string) $event->description);
        $this->assertSame('Povodny short', (string) $event->short);
    }

    public function test_generate_description_dry_run_requires_sync_mode(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $event = Event::query()->create([
            'title' => 'Dry run invalid mode',
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-04-12 20:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-04-12 20:00:00', 'UTC'),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'evt-admin-ai-dry-run-invalid',
            'source_hash' => hash('sha256', 'evt-admin-ai-dry-run-invalid'),
        ]);

        $this->postJson('/api/admin/events/' . $event->id . '/ai/generate-description', [
            'sync' => false,
            'dry_run' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dry_run']);
    }

    public function test_config_endpoint_returns_sanitized_last_run_payload_without_sensitive_fields(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $event = Event::query()->create([
            'title' => 'Moon conjunction',
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-02-28 19:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-02-28 19:15:00', 'UTC'),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'evt-admin-ai-config-1',
            'source_hash' => hash('sha256', 'evt-admin-ai-config-1'),
        ]);

        app(AiLastRunStore::class)->put(
            featureName: 'event_description_generate',
            status: 'success',
            latencyMs: 84,
            entityId: (int) $event->id,
            retryCount: 2
        );

        $response = $this->getJson('/api/admin/ai/config?event_id=' . $event->id)
            ->assertOk();

        $lastRun = (array) data_get($response->json(), 'data.features.event_description_generate.last_run', []);
        $this->assertSame([
            'feature_name',
            'status',
            'latency_ms',
            'retry_count',
            'entity_id',
            'event_id',
            'updated_at',
        ], array_keys($lastRun));
        $this->assertArrayNotHasKey('prompt', $lastRun);
        $this->assertArrayNotHasKey('raw_text', $lastRun);
        $this->assertArrayNotHasKey('meta', $lastRun);
    }

    public function test_ai_policy_endpoint_returns_effective_policy_payload(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/ai/policy')
            ->assertOk();

        $this->assertIsArray($response->json('data.effective.prompts.legacy.rules'));
        $this->assertIsArray($response->json('data.effective.prompts.humanized.rules'));
        $this->assertIsArray($response->json('data.effective.safety.celestial_terms'));
        $this->assertIsArray($response->json('data.effective.safety.forbidden_substrings'));
        $this->assertIsArray($response->json('data.effective.safety.forbidden_regex'));
    }

    public function test_ai_policy_patch_rejects_invalid_regex_pattern(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/ai/policy', [
            'policy' => [
                'safety' => [
                    'forbidden_regex' => [
                        '/(/',
                    ],
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'policy.safety.forbidden_regex.0',
            ]);
    }

    public function test_ai_policy_patch_persists_forbidden_regex_override(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/ai/policy', [
            'policy' => [
                'safety' => [
                    'forbidden_regex' => [
                        '/\\bmeteorit\\s+daj\\b/iu',
                        'hviezdne\\s+metro',
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.has_override', true)
            ->assertJsonPath('data.effective.safety.forbidden_regex.0', '/\\bmeteorit\\s+daj\\b/iu')
            ->assertJsonPath('data.effective.safety.forbidden_regex.1', 'hviezdne\\s+metro');
    }

    public function test_ai_policy_patch_replaces_list_values_instead_of_appending_old_entries(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/ai/policy', [
            'policy' => [
                'safety' => [
                    'forbidden_regex' => [
                        'alpha',
                        'beta',
                    ],
                ],
            ],
        ])->assertOk();

        $this->patchJson('/api/admin/ai/policy', [
            'policy' => [
                'safety' => [
                    'forbidden_regex' => [
                        'gamma',
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.effective.safety.forbidden_regex.0', 'gamma')
            ->assertJsonCount(1, 'data.effective.safety.forbidden_regex');
    }

    public function test_blog_tag_suggestions_valid_response_returns_existing_tags(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-1');
        $mars = Tag::query()->create(['name' => 'Mars']);
        $moon = Tag::query()->create(['name' => 'Mesiac']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Mars', 'reason' => 'Clanok spomina pozorovanie planety Mars.'],
                            ['name' => 'Mesiac', 'reason' => 'Obsah sa venuje nocnemu pozorovaniu oblohy.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 52,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('fallback_used', false)
            ->assertJsonPath('last_run.feature_name', 'blog_tag_suggestions')
            ->assertJsonPath('last_run.entity_id', (int) $post->id)
            ->assertJsonPath('last_run.status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(2, $tags);
        $this->assertSame($mars->id, (int) data_get($tags, '0.id'));
        $this->assertSame('Mars', (string) data_get($tags, '0.name'));
        $this->assertSame($moon->id, (int) data_get($tags, '1.id'));
        $this->assertSame('Mesiac', (string) data_get($tags, '1.name'));
    }

    public function test_blog_tag_suggestions_without_existing_tags_returns_ai_generated_candidates(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-open');
        $this->assertSame(0, Tag::query()->count());

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Mars', 'reason' => 'Clanok sa zameriava na planetu Mars.'],
                            ['name' => 'Planety', 'reason' => 'Obsah porovnava viditelnost planet.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 48,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags', [
            'mode' => 'allow_new',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('fallback_used', false);

        $tags = (array) $response->json('tags');
        $this->assertCount(2, $tags);
        $this->assertSame(0, (int) data_get($tags, '0.id'));
        $this->assertSame('Mars', (string) data_get($tags, '0.name'));
        $this->assertSame(0, (int) data_get($tags, '1.id'));
        $this->assertSame('Planety', (string) data_get($tags, '1.name'));
    }

    public function test_blog_tag_suggestions_existing_only_mode_without_existing_tags_returns_reason(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-existing-only-empty');
        $this->assertSame(0, Tag::query()->count());

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')->never();
        });

        $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags', [
            'mode' => 'existing_only',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'fallback')
            ->assertJsonPath('fallback_used', true)
            ->assertJsonPath('reason', 'no_existing_tags')
            ->assertJsonCount(0, 'tags');
    }

    public function test_blog_tag_suggestions_allow_new_mode_maps_similar_name_to_existing_tag(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-smart-map');
        $mars = Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Marss', 'reason' => 'Nazov je podobny existujucemu tagu Mars.'],
                            ['name' => 'Planety', 'reason' => 'Doplnujuci novy tag k teme clanku.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 27,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags', [
            'mode' => 'allow_new',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('fallback_used', false);

        $tags = (array) $response->json('tags');
        $this->assertCount(2, $tags);
        $this->assertSame((int) $mars->id, (int) data_get($tags, '0.id'));
        $this->assertSame('Mars', (string) data_get($tags, '0.name'));
        $this->assertSame(0, (int) data_get($tags, '1.id'));
        $this->assertSame('Planety', (string) data_get($tags, '1.name'));
    }

    public function test_blog_tag_suggestions_match_existing_tag_with_different_casing_and_spacing(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-casing');
        $mars = Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => '  mArS  ', 'reason' => 'Relevantny tag pre text o planete Mars.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 31,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame($mars->id, (int) data_get($tags, '0.id'));
        $this->assertSame('Mars', (string) data_get($tags, '0.name'));
    }

    public function test_blog_tag_suggestions_match_existing_tag_without_diacritics(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-diacritics');
        $accentedTagName = "\u{0160}kvrny";
        $accentedTag = Tag::query()->create(['name' => $accentedTagName]);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Skvrny', 'reason' => 'Tema clanku sa dotyka slnecnych skvrn.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 30,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame($accentedTag->id, (int) data_get($tags, '0.id'));
        $this->assertSame($accentedTagName, (string) data_get($tags, '0.name'));
    }

    public function test_blog_tag_suggestions_collision_mapping_prefers_lowest_id(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-collision');
        $first = Tag::query()->create(['name' => 'Skvrny']);
        $second = Tag::query()->create(['name' => "\u{0160}kvrny"]);
        $this->assertLessThan($second->id, $first->id);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Skvrny', 'reason' => 'Clanok sa venuje slnecnym skvrnam.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 23,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame((int) $first->id, (int) data_get($tags, '0.id'));
        $this->assertSame((string) $first->name, (string) data_get($tags, '0.name'));
    }

    public function test_blog_tag_suggestions_filter_non_existing_tags(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-2');
        $mars = Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Mars', 'reason' => 'Clanok sa venuje planete Mars.'],
                            ['name' => 'Neexistujuci', 'reason' => 'Tento tag v databaze nie je.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 38,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('fallback_used', false);

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame($mars->id, (int) data_get($tags, '0.id'));
        $this->assertSame('Mars', (string) data_get($tags, '0.name'));
    }

    public function test_blog_tag_suggestions_truncates_reason_to_max_length(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-reason-limit');
        $mars = Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            [
                                'name' => 'Mars',
                                'reason' => str_repeat('r', 180),
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 19,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame($mars->id, (int) data_get($tags, '0.id'));
        $this->assertSame(120, function_exists('mb_strlen')
            ? mb_strlen((string) data_get($tags, '0.reason'), 'UTF-8')
            : strlen((string) data_get($tags, '0.reason')));
    }

    public function test_blog_tag_suggestions_truncates_reason_utf8_safely(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-reason-utf8');
        $moon = Tag::query()->create(['name' => 'Mesiac']);
        $longUtf8Reason = str_repeat("\u{017E}", 150);

        $this->mock(OllamaClient::class, function ($mock) use ($longUtf8Reason): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            [
                                'name' => 'Mesiac',
                                'reason' => $longUtf8Reason,
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 17,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $tags = (array) $response->json('tags');
        $this->assertCount(1, $tags);
        $this->assertSame($moon->id, (int) data_get($tags, '0.id'));
        $reason = (string) data_get($tags, '0.reason', '');

        $this->assertSame(120, function_exists('mb_strlen')
            ? mb_strlen($reason, 'UTF-8')
            : strlen($reason));
        if (function_exists('mb_check_encoding')) {
            $this->assertTrue(mb_check_encoding($reason, 'UTF-8'));
        }
    }

    public function test_blog_tag_suggestions_enforces_max_five_results(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-limit-five');
        for ($index = 1; $index <= 6; $index++) {
            Tag::query()->create(['name' => 'Tag' . $index]);
        }

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Tag1', 'reason' => 'R1'],
                            ['name' => 'Tag2', 'reason' => 'R2'],
                            ['name' => 'Tag3', 'reason' => 'R3'],
                            ['name' => 'Tag4', 'reason' => 'R4'],
                            ['name' => 'Tag5', 'reason' => 'R5'],
                            ['name' => 'Tag6', 'reason' => 'R6'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 26,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $resultTags = (array) $response->json('tags');
        $this->assertCount(5, $resultTags);
        $this->assertSame(['Tag1', 'Tag2', 'Tag3', 'Tag4', 'Tag5'], array_values(array_map(
            static fn (array $row): string => (string) data_get($row, 'name', ''),
            $resultTags
        )));
    }

    public function test_blog_tag_suggestions_invalid_json_uses_fallback(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-3');
        $mars = Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'invalid-json',
                    'model' => 'mistral',
                    'duration_ms' => 34,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk()
            ->assertJsonPath('status', 'fallback')
            ->assertJsonPath('fallback_used', true)
            ->assertJsonPath('last_run.status', 'fallback');

        $response
            ->assertJsonCount(1, 'tags')
            ->assertJsonPath('tags.0.id', (int) $mars->id)
            ->assertJsonPath('tags.0.name', 'Mars');
    }

    public function test_admin_blog_post_update_tags_deduplicates_case_and_diacritics(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-sync-dedupe');
        $existing = Tag::query()->create(['name' => "\u{0160}kvrny"]);

        $this->assertSame(1, Tag::query()->count());

        $response = $this->putJson('/api/admin/blog-posts/' . $post->id, [
            'tags' => ['Skvrny', '  Škvrny  ', 'SKVRNY'],
        ])
            ->assertOk()
            ->assertJsonPath('tag_sync.created_new', 0)
            ->assertJsonPath('tag_sync.attached_existing', 1)
            ->assertJsonPath('tag_sync.added_total', 1)
            ->assertJsonPath('tag_sync.selected_total', 1);

        $this->assertSame(1, Tag::query()->count());
        $this->assertSame(1, $post->tags()->count());
        $this->assertSame((int) $existing->id, (int) data_get($response->json('tags'), '0.id'));
        $this->assertSame((string) $existing->name, (string) data_get($response->json('tags'), '0.name'));
    }

    public function test_blog_tag_suggestions_endpoint_is_runtime_rate_limited(): void
    {
        config()->set('admin.ai_rate_limit_per_minute', 2);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $post = $this->createTaggableBlogPost('blog-tag-ai-4');
        Tag::query()->create(['name' => 'Mars']);

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->twice()
                ->andReturn([
                    'text' => json_encode([
                        'tags' => [
                            ['name' => 'Mars', 'reason' => 'Relevantne pre obsah.'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'model' => 'mistral',
                    'duration_ms' => 21,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $ip = '10.0.0.88';
        RateLimiter::clear('admin-ai|' . $admin->id . '|' . $ip);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/admin/blog-posts/' . $post->id . '/ai/suggest-tags')
            ->assertStatus(429);
    }

    private function createTaggableBlogPost(string $slug): BlogPost
    {
        $author = User::factory()->create();

        return BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'AI navrhy tagov pre blog',
            'slug' => $slug,
            'content' => 'Clanok o pozorovani oblohy, planetach a praktickych tipoch pre pozorovanie.',
            'published_at' => CarbonImmutable::now('UTC')->subHours(6),
            'views' => 15,
        ]);
    }
}

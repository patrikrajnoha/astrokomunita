<?php

namespace Tests\Feature;

use App\Enums\BotRunFailureReason;
use App\Models\AppSetting;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\Post;
use App\Models\User;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Illuminate\Support\Facades\Http;

class AdminBotControllerTranslationAndPublishingTest extends AdminBotControllerTestCase
{
    public function test_admin_translation_test_endpoint_returns_provider_and_translated_text(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');
        config()->set('bots.translation.quality.enabled', false);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translatedText' => 'SK Test translation payload.',
            ], 200),
        ]);

        $response = $this->postJson('/api/admin/bots/translation/test', [
            'text' => 'Test translation payload.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'libretranslate')
            ->assertJsonPath('translated_text', 'SK Test translation payload.')
            ->assertJsonPath('mode', 'lt_only')
            ->assertJsonPath('provider_chain.0', 'libretranslate');

        $this->assertGreaterThanOrEqual(0, (int) $response->json('latency_ms'));
    }

    public function test_admin_translation_test_endpoint_applies_requested_provider_model_and_temperature(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.quality.enabled', false);
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'custom-model',
                'response' => 'Toto je test prekladu.',
                'done' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/admin/bots/translation/test', [
            'text' => 'This is a translation test.',
            'provider' => 'ollama',
            'model' => 'custom-model',
            'temperature' => 0.4,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'ollama')
            ->assertJsonPath('meta.requested_provider', 'ollama')
            ->assertJsonPath('meta.requested_model', 'custom-model')
            ->assertJsonPath('meta.requested_temperature', 0.4);

        Http::assertSent(static function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'http://ollama.test/api/generate'
                && data_get($payload, 'model') === 'custom-model'
                && (float) data_get($payload, 'options.temperature') === 0.4;
        });
    }

    public function test_admin_translation_test_endpoint_maps_timeout_to_structured_failure_reason(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationTimeoutException('ollama', 'Timeout during test.');
                }
            };
        });

        $response = $this->postJson('/api/admin/bots/translation/test', ['text' => 'Hello']);
        $response
            ->assertStatus(504)
            ->assertJsonPath('failure_reason', BotRunFailureReason::TRANSLATION_TIMEOUT->value);
    }

    public function test_admin_translation_test_endpoint_returns_only_known_failure_reason_values(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new \RuntimeException('Unknown translation failure');
                }
            };
        });

        $response = $this->postJson('/api/admin/bots/translation/test', ['text' => 'Hello']);
        $reason = (string) $response->json('failure_reason');

        $response->assertStatus(422);
        $this->assertNotNull(BotRunFailureReason::tryFrom($reason));
    }

    public function test_admin_translation_health_endpoint_returns_provider_config_without_secrets(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 12);
        config()->set('bots.translation.libretranslate.url', 'http://localhost:5000');

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    return [
                        'translated_title' => 'ok',
                        'translated_content' => null,
                        'title_translated' => 'ok',
                        'content_translated' => null,
                        'status' => 'done',
                        'meta' => ['provider' => 'libretranslate'],
                    ];
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('provider', 'libretranslate')
            ->assertJsonPath('simulate_outage_provider', 'none')
            ->assertJsonPath('degraded', false)
            ->assertJsonPath('result.ok', true)
            ->assertJsonMissingPath('api_key');
    }

    public function test_admin_translation_health_endpoint_reports_provider_unavailable_error_type(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationProviderUnavailableException('libretranslate', 'Cannot connect');
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('degraded', false)
            ->assertJsonPath('result.ok', false)
            ->assertJsonPath('result.error_type', BotRunFailureReason::PROVIDER_UNAVAILABLE->value);
    }

    public function test_admin_translation_health_endpoint_reports_degraded_when_primary_fails_but_fallback_succeeds(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'ollama');
        config()->set('bots.translation.fallback', 'libretranslate');
        AppSetting::put('translation.simulate_outage_provider', 'ollama');

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    $currentProvider = strtolower(trim((string) config('bots.translation.primary', '')));
                    $simulatedOutageProvider = strtolower(trim((string) AppSetting::getString('translation.simulate_outage_provider', 'none')));
                    if ($simulatedOutageProvider !== 'none' && $simulatedOutageProvider === $currentProvider) {
                        throw new TranslationProviderUnavailableException('ollama', 'Primary provider unavailable');
                    }

                    return [
                        'translated_title' => 'ok',
                        'translated_content' => null,
                        'title_translated' => 'ok',
                        'content_translated' => null,
                        'status' => 'done',
                        'meta' => ['provider' => 'libretranslate'],
                    ];
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('simulate_outage_provider', 'ollama')
            ->assertJsonPath('degraded', true)
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.error_type', null)
            ->assertJsonPath('result.primary_error_type', BotRunFailureReason::PROVIDER_UNAVAILABLE->value);
    }

    public function test_admin_can_update_translation_simulate_outage_setting(): void
    {
        $this->actingAsAdmin();
        AppSetting::put('translation.simulate_outage_provider', 'none');

        $response = $this->postJson('/api/admin/bots/translation/simulate-outage', [
            'provider' => 'ollama',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('key', 'translation.simulate_outage_provider')
            ->assertJsonPath('old_value', 'none')
            ->assertJsonPath('new_value', 'ollama');

        $this->assertSame('ollama', AppSetting::getString('translation.simulate_outage_provider', 'none'));
    }

    public function test_admin_retry_translation_retries_failed_items_and_marks_them_done(): void
    {
        $source = $this->createRssSource('retry_translation_source');
        $failedItem = $this->createBotItem($source, 'retry-1', now(), [
            'translation_status' => 'failed',
            'translation_error' => 'previous_failure',
            'translation_provider' => null,
            'title' => 'English retry title',
            'content' => 'English retry body long enough to pass validation checks.',
        ]);

        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $response = $this->postJson('/api/admin/bots/translation/retry/' . $source->key . '?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('retried_count', 1)
            ->assertJsonPath('done_count', 1)
            ->assertJsonPath('failed_count', 0);

        $failedItem->refresh();
        $this->assertSame('done', (string) $failedItem->translation_status->value);
        $this->assertSame('libretranslate', (string) $failedItem->translation_provider);
        $this->assertNull($failedItem->translation_error);
        $this->assertNotNull($failedItem->translated_at);
        $this->assertSame('SK English retry title', (string) $failedItem->title_translated);
    }

    public function test_admin_translation_backfill_updates_existing_post_without_duplicates(): void
    {
        $source = $this->createRssSource('backfill_translation_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmobot',
            'email' => 'kozmobot-backfill@example.test',
        ]);
        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'NASA | English backfill title' . "\n\n" . 'English backfill body long enough for publish.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1($source->key . '|guid-backfill-admin'),
            'source_url' => 'https://example.test/news/guid-backfill-admin',
            'translation_status' => 'pending',
        ]);

        $item = $this->createBotItem($source, 'guid-backfill-admin', now(), [
            'post_id' => $post->id,
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'title' => 'English backfill title',
            'content' => 'English backfill body long enough for publish.',
            'url' => 'https://example.test/news/guid-backfill-admin',
        ]);

        $this->actingAsAdmin();
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'Ahoj svet ' . $sourceText,
                ], 200);
            },
        ]);

        $response = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('scanned', 1)
            ->assertJsonPath('updated_posts', 1)
            ->assertJsonPath('failed', 0);

        $item->refresh();
        $post->refresh();
        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertNotNull($item->translated_at);
        $this->assertStringContainsString('Ahoj svet', (string) $post->content);
        $this->assertSame('done', (string) $post->translation_status);
        $this->assertSame(1, Post::query()->count());
    }

    public function test_admin_translation_backfill_is_idempotent_on_second_call(): void
    {
        $source = $this->createRssSource('backfill_translation_idempotent_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmobot2',
            'email' => 'kozmobot-backfill2@example.test',
        ]);
        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'NASA | English idempotent title' . "\n\n" . 'English idempotent body long enough for publish.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1($source->key . '|guid-backfill-idempotent'),
            'source_url' => 'https://example.test/news/guid-backfill-idempotent',
            'translation_status' => 'pending',
        ]);

        $this->createBotItem($source, 'guid-backfill-idempotent', now(), [
            'post_id' => $post->id,
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'title' => 'English idempotent title',
            'content' => 'English idempotent body long enough for publish.',
            'url' => 'https://example.test/news/guid-backfill-idempotent',
        ]);

        $this->actingAsAdmin();
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'Ahoj svet ' . $sourceText,
                ], 200);
            },
        ]);

        $first = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');
        $second = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');

        $first
            ->assertOk()
            ->assertJsonPath('updated_posts', 1);

        $second
            ->assertOk()
            ->assertJsonPath('updated_posts', 0);
    }

    public function test_admin_publish_item_endpoint_publishes_pending_item_and_marks_manual_audit(): void
    {
        $source = $this->createRssSource('publish_single_source');
        $item = $this->createBotItem($source, 'publish-single', now(), [
            'run_id' => null,
            'publish_status' => 'pending',
            'translation_status' => 'done',
            'title' => 'Publish single item',
            'content' => 'Content long enough for publish validation to pass quickly.',
            'url' => 'https://example.test/publish-single',
        ]);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/bots/items/' . $item->id . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('item.id', $item->id)
            ->assertJsonPath('item.publish_status', 'published')
            ->assertJsonPath('item.published_manually', true);

        $item->refresh();
        $this->assertNotNull($item->post_id);
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertTrue((bool) data_get($item->meta, 'published_manually'));
        $this->assertNotSame('', (string) data_get($item->meta, 'manual_published_at'));
    }

    public function test_admin_publish_item_endpoint_rejects_skipped_item(): void
    {
        $source = $this->createRssSource('publish_single_skipped_source');
        $item = $this->createBotItem($source, 'publish-single-skipped', now(), [
            'publish_status' => 'skipped',
            'meta' => ['skip_reason' => 'missing_title_or_url'],
        ]);

        $this->actingAsAdmin();

        $this->postJson('/api/admin/bots/items/' . $item->id . '/publish')
            ->assertStatus(422)
            ->assertJsonPath('skip_reason', 'missing_title_or_url');
    }

    public function test_admin_publish_run_endpoint_respects_publish_limit(): void
    {
        $source = $this->createRssSource('publish_run_source');
        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
            'status' => 'success',
            'stats' => ['published_count' => 0],
            'error_text' => null,
            'meta' => ['run_context' => 'admin', 'mode' => 'dry'],
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->createBotItem($source, 'publish-run-' . $i, now()->addSeconds($i), [
                'run_id' => $run->id,
                'publish_status' => 'pending',
                'translation_status' => 'done',
                'title' => 'Publish run item ' . $i,
                'content' => 'Content long enough for publish validation number ' . $i . '.',
                'url' => 'https://example.test/publish-run-' . $i,
            ]);
        }

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/bots/runs/' . $run->id . '/publish', [
            'publish_limit' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('run_id', $run->id)
            ->assertJsonPath('publish_limit', 2)
            ->assertJsonPath('attempted_count', 2)
            ->assertJsonPath('published_count', 2);

        $this->assertSame(2, BotItem::query()->where('run_id', $run->id)->whereNotNull('post_id')->count());
        $this->assertSame(1, BotItem::query()->where('run_id', $run->id)->whereNull('post_id')->count());
    }

    public function test_admin_delete_item_post_endpoint_deletes_post_and_unlinks_item(): void
    {
        $source = $this->createRssSource('delete_item_post_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmo',
            'email' => 'kozmo.delete@example.test',
        ]);

        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Bot post for delete endpoint test.',
            'moderation_status' => 'ok',
        ]);

        $item = $this->createBotItem($source, 'delete-item-post', now(), [
            'post_id' => $post->id,
            'publish_status' => 'published',
            'translation_status' => 'done',
            'meta' => [
                'published_manually' => true,
            ],
        ]);

        $this->actingAsAdmin();

        $response = $this->deleteJson('/api/admin/bots/items/' . $item->id . '/post');

        $response
            ->assertOk()
            ->assertJsonPath('deleted_post_id', $post->id)
            ->assertJsonPath('item.id', $item->id)
            ->assertJsonPath('item.post_id', null)
            ->assertJsonPath('item.publish_status', 'pending');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);

        $item->refresh();
        $this->assertNull($item->post_id);
        $this->assertSame('pending', (string) $item->publish_status->value);
        $this->assertTrue((bool) data_get($item->meta, 'deleted_manually'));
        $this->assertSame($post->id, (int) data_get($item->meta, 'deleted_post_id'));
    }

    public function test_admin_delete_item_post_endpoint_rejects_item_without_post_link(): void
    {
        $source = $this->createRssSource('delete_item_no_post_source');
        $item = $this->createBotItem($source, 'delete-item-no-post', now(), [
            'post_id' => null,
            'publish_status' => 'pending',
        ]);

        $this->actingAsAdmin();

        $this->deleteJson('/api/admin/bots/items/' . $item->id . '/post')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Položka nemá publikovaný príspevok na vymazanie.');
    }

    public function test_admin_can_get_and_update_bot_post_retention_settings(): void
    {
        $this->actingAsAdmin();
        AppSetting::put('bots.posts.auto_delete_enabled', '0');
        AppSetting::put('bots.posts.auto_delete_after_hours', '48');

        $this->getJson('/api/admin/bots/post-retention')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.auto_delete_after_hours', 48);

        $this->patchJson('/api/admin/bots/post-retention', [
            'enabled' => true,
            'auto_delete_after_hours' => 24,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.auto_delete_after_hours', 24);

        $this->assertSame(true, AppSetting::getBool('bots.posts.auto_delete_enabled', false));
        $this->assertSame(24, AppSetting::getInt('bots.posts.auto_delete_after_hours', 48));
    }

    public function test_admin_can_run_bot_post_retention_cleanup_and_it_unlinks_items(): void
    {
        $source = $this->createRssSource('retention_cleanup_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => 'retentionbot',
            'email' => null,
        ]);

        $oldPost = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Old bot post for retention cleanup.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1('retention-old'),
            'moderation_status' => 'ok',
        ]);
        $oldPost->forceFill([
            'created_at' => now()->subHours(60),
            'updated_at' => now()->subHours(60),
        ])->save();

        $freshPost = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Fresh bot post for retention cleanup.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1('retention-fresh'),
            'moderation_status' => 'ok',
        ]);

        $oldItem = $this->createBotItem($source, 'retention-old-item', now()->subHours(60), [
            'post_id' => $oldPost->id,
            'publish_status' => 'published',
            'translation_status' => 'done',
        ]);
        $freshItem = $this->createBotItem($source, 'retention-fresh-item', now()->subHours(2), [
            'post_id' => $freshPost->id,
            'publish_status' => 'published',
            'translation_status' => 'done',
        ]);

        AppSetting::put('bots.posts.auto_delete_enabled', '1');
        AppSetting::put('bots.posts.auto_delete_after_hours', '24');
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/bots/post-retention/cleanup', [
            'limit' => 200,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.deleted_posts', 1)
            ->assertJsonPath('data.failed_items', 0)
            ->assertJsonPath('data.retention_hours', 24);

        $this->assertDatabaseMissing('posts', ['id' => $oldPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $freshPost->id]);

        $oldItem->refresh();
        $freshItem->refresh();

        $this->assertNull($oldItem->post_id);
        $this->assertSame('pending', (string) ($oldItem->publish_status?->value ?? $oldItem->publish_status));
        $this->assertTrue((bool) data_get($oldItem->meta, 'deleted_by_retention'));

        $this->assertSame($freshPost->id, (int) $freshItem->post_id);
        $this->assertSame('published', (string) ($freshItem->publish_status?->value ?? $freshItem->publish_status));
    }
}

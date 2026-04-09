<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObservationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_observation_requires_authentication(): void
    {
        $this->fakePublicMediaDisk();

        $response = $this->post('/api/observations', $this->validPayload(), [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('observations', 0);
    }

    public function test_create_observation_validates_required_fields(): void
    {
        $this->fakePublicMediaDisk();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/observations', [
            'description' => 'Missing required fields',
            'images' => [$this->fakeImage()],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'observed_at']);
    }

    public function test_create_observation_enforces_configured_image_limit(): void
    {
        $this->fakePublicMediaDisk();
        config()->set('media.observation_image_max_count', 2);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/observations', $this->validPayload([
            'images' => [
                $this->fakeImage('a.png'),
                $this->fakeImage('b.png'),
                $this->fakeImage('c.png'),
            ],
        ]), [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    public function test_owner_can_update_and_delete_observation_while_non_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $observation = Observation::factory()->for($owner)->create([
            'title' => 'Original title',
            'is_public' => true,
        ]);

        Sanctum::actingAs($stranger);
        $this->patchJson("/api/observations/{$observation->id}", [
            'title' => 'Hijacked title',
        ])->assertStatus(403);

        $this->deleteJson("/api/observations/{$observation->id}")
            ->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/observations/{$observation->id}", [
            'title' => 'Updated title',
        ])->assertOk()->assertJsonPath('title', 'Updated title');

        $this->assertDatabaseHas('observations', [
            'id' => $observation->id,
            'title' => 'Updated title',
        ]);

        $this->deleteJson("/api/observations/{$observation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('observations', ['id' => $observation->id]);
    }

    public function test_list_mine_returns_only_authenticated_users_observations(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $mineA = Observation::factory()->for($me)->create(['title' => 'Mine A']);
        $mineB = Observation::factory()->for($me)->create(['title' => 'Mine B']);
        $otherObs = Observation::factory()->for($other)->create(['title' => 'Other']);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/observations?mine=1');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertTrue($ids->contains((int) $mineA->id));
        $this->assertTrue($ids->contains((int) $mineB->id));
        $this->assertFalse($ids->contains((int) $otherObs->id));
    }

    public function test_list_by_user_id_returns_only_public_observations_for_requested_user(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();

        $targetPublic = Observation::factory()->for($target)->create([
            'title' => 'Target public',
            'is_public' => true,
        ]);
        $targetPrivate = Observation::factory()->for($target)->create([
            'title' => 'Target private',
            'is_public' => false,
        ]);
        $otherPublic = Observation::factory()->for($other)->create([
            'title' => 'Other public',
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/observations?user_id=' . $target->id);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertTrue($ids->contains((int) $targetPublic->id));
        $this->assertFalse($ids->contains((int) $targetPrivate->id));
        $this->assertFalse($ids->contains((int) $otherPublic->id));
    }

    public function test_public_only_filter_hides_private_observations_for_authenticated_owner(): void
    {
        $target = User::factory()->create();

        $targetPublic = Observation::factory()->for($target)->create([
            'title' => 'Target public',
            'is_public' => true,
        ]);
        $targetPrivate = Observation::factory()->for($target)->create([
            'title' => 'Target private',
            'is_public' => false,
        ]);

        Sanctum::actingAs($target);

        $defaultResponse = $this->getJson('/api/observations?user_id=' . $target->id);
        $defaultResponse->assertOk();

        $defaultIds = collect($defaultResponse->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue($defaultIds->contains((int) $targetPublic->id));
        $this->assertTrue($defaultIds->contains((int) $targetPrivate->id));

        $publicOnlyResponse = $this->getJson('/api/observations?user_id=' . $target->id . '&public_only=1');
        $publicOnlyResponse->assertOk();

        $publicOnlyIds = collect($publicOnlyResponse->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue($publicOnlyIds->contains((int) $targetPublic->id));
        $this->assertFalse($publicOnlyIds->contains((int) $targetPrivate->id));
        $this->assertSame(1, (int) $publicOnlyResponse->json('total'));
    }

    public function test_list_supports_oldest_sort_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $oldest = Observation::factory()->for($user)->create([
            'title' => 'Oldest',
            'observed_at' => now()->subDays(3),
        ]);
        $newest = Observation::factory()->for($user)->create([
            'title' => 'Newest',
            'observed_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/observations?mine=1&sort=oldest');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->values();
        $this->assertSame((int) $oldest->id, (int) ($ids->first() ?? 0));
        $this->assertSame((int) $newest->id, (int) ($ids->last() ?? 0));
    }

    public function test_list_sort_order_is_deterministic_by_observed_at_then_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sameObservedAt = now()->subDay()->startOfMinute();

        $first = Observation::factory()->for($user)->create([
            'title' => 'First',
            'observed_at' => $sameObservedAt,
        ]);
        $second = Observation::factory()->for($user)->create([
            'title' => 'Second',
            'observed_at' => $sameObservedAt,
        ]);
        $third = Observation::factory()->for($user)->create([
            'title' => 'Third',
            'observed_at' => $sameObservedAt,
        ]);

        $newest = $this->getJson('/api/observations?mine=1&sort=newest');
        $newest->assertOk();
        $newestIds = collect($newest->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->values();
        $this->assertSame([(int) $third->id, (int) $second->id, (int) $first->id], $newestIds->take(3)->all());

        $oldest = $this->getJson('/api/observations?mine=1&sort=oldest');
        $oldest->assertOk();
        $oldestIds = collect($oldest->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->values();
        $this->assertSame([(int) $first->id, (int) $second->id, (int) $third->id], $oldestIds->take(3)->all());
    }

    public function test_creating_public_observation_creates_feed_item_visible_to_creator_and_others(): void
    {
        $this->fakePublicMediaDisk();

        $creator = User::factory()->create();
        Sanctum::actingAs($creator);

        $create = $this->post('/api/observations', $this->validPayload([
            'title' => 'First Light',
            'description' => 'Clear sky and excellent seeing.',
            'is_public' => true,
        ]), [
            'Accept' => 'application/json',
        ]);

        $create->assertCreated();

        $observationId = (int) $create->json('id');
        $feedPostId = (int) $create->json('feed_post_id');

        $this->assertGreaterThan(0, $observationId);
        $this->assertGreaterThan(0, $feedPostId);

        $this->assertDatabaseHas('observations', [
            'id' => $observationId,
            'title' => 'First Light',
            'is_public' => 1,
            'feed_post_id' => $feedPostId,
        ]);
        $this->assertDatabaseHas('observation_media', [
            'observation_id' => $observationId,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $feedPostId,
            'source_name' => 'observation',
            'source_uid' => 'observation-' . $observationId,
        ]);

        $creatorFeed = $this->getJson('/api/feed?with=counts');
        $creatorFeed->assertOk();

        $creatorItem = collect($creatorFeed->json('data'))
            ->first(fn (array $item): bool => (int) data_get($item, 'meta.observation.observation_id') === $observationId);

        $this->assertIsArray($creatorItem);
        $this->assertSame($observationId, (int) data_get($creatorItem, 'attached_observation.id'));
        $this->assertSame('observation', (string) data_get($creatorItem, 'feed_item_type'));

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $otherFeed = $this->getJson('/api/feed?with=counts');
        $otherFeed->assertOk();

        $otherSeesObservation = collect($otherFeed->json('data'))
            ->contains(fn (array $item): bool => (int) data_get($item, 'meta.observation.observation_id') === $observationId);

        $this->assertTrue($otherSeesObservation);
    }

    public function test_updating_observation_refreshes_mirrored_post_preview_fields(): void
    {
        $this->fakePublicMediaDisk();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $create = $this->post('/api/observations', $this->validPayload([
            'title' => 'Initial title',
            'description' => 'Initial description.',
            'images' => [$this->fakeImage('initial.png')],
        ]), [
            'Accept' => 'application/json',
        ]);
        $create->assertCreated();

        $observationId = (int) $create->json('id');
        $feedPostId = (int) $create->json('feed_post_id');

        $this->patchJson("/api/observations/{$observationId}", [
            'title' => 'Updated title',
            'description' => 'Updated description for feed preview.',
            'images' => [$this->fakeImage('new.png')],
        ])->assertOk();

        $post = Post::query()->find($feedPostId);
        $this->assertNotNull($post);
        $this->assertSame('Updated title', (string) data_get($post?->meta, 'observation.title'));
        $this->assertSame(
            'Updated description for feed preview.',
            (string) data_get($post?->meta, 'observation.description_excerpt')
        );
        $this->assertSame(2, (int) data_get($post?->meta, 'observation.media_count'));
        $this->assertStringContainsString('Updated title', (string) $post?->content);
    }

    public function test_deleting_observation_removes_mirrored_post_feed_item_and_media(): void
    {
        $this->fakePublicMediaDisk();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $create = $this->post('/api/observations', $this->validPayload([
            'title' => 'To delete',
            'description' => 'Delete me',
            'is_public' => true,
        ]), [
            'Accept' => 'application/json',
        ]);
        $create->assertCreated();

        $observationId = (int) $create->json('id');
        $feedPostId = (int) $create->json('feed_post_id');
        $mediaPath = trim((string) $create->json('media.0.path'));

        $this->assertDatabaseHas('posts', ['id' => $feedPostId]);
        $this->assertNotSame('', $mediaPath);
        $this->assertTrue(Storage::disk('public')->exists($mediaPath));

        $this->deleteJson("/api/observations/{$observationId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('observations', ['id' => $observationId]);
        $this->assertDatabaseMissing('posts', ['id' => $feedPostId]);
        $this->assertDatabaseMissing('observation_media', ['observation_id' => $observationId]);
        $this->assertFalse(Storage::disk('public')->exists($mediaPath));

        Sanctum::actingAs(User::factory()->create());
        $feed = $this->getJson('/api/feed?with=counts');
        $feed->assertOk();

        $stillVisible = collect($feed->json('data'))
            ->contains(fn (array $item): bool => (int) data_get($item, 'meta.observation.observation_id') === $observationId);

        $this->assertFalse($stillVisible);
    }

    public function test_public_detail_hides_precise_location_for_non_owner(): void
    {
        $owner = User::factory()->create();

        $observation = Observation::factory()->for($owner)->create([
            'is_public' => true,
            'location_lat' => 48.1486,
            'location_lng' => 17.1077,
        ]);

        $this->getJson("/api/observations/{$observation->id}")
            ->assertOk()
            ->assertJsonPath('location_lat', null)
            ->assertJsonPath('location_lng', null);

        Sanctum::actingAs($owner);
        $ownerResponse = $this->getJson("/api/observations/{$observation->id}");
        $ownerResponse->assertOk();

        $this->assertEqualsWithDelta((float) $observation->location_lat, (float) $ownerResponse->json('location_lat'), 0.000001);
        $this->assertEqualsWithDelta((float) $observation->location_lng, (float) $ownerResponse->json('location_lng'), 0.000001);
    }

    public function test_update_observation_enforces_total_media_limit(): void
    {
        $this->fakePublicMediaDisk();
        config()->set('media.observation_image_max_count', 2);

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $create = $this->post('/api/observations', $this->validPayload([
            'images' => [$this->fakeImage('start.png')],
        ]), [
            'Accept' => 'application/json',
        ]);
        $create->assertCreated();
        $observationId = (int) $create->json('id');

        $this->patchJson("/api/observations/{$observationId}", [
            'images' => [
                $this->fakeImage('next-1.png'),
                $this->fakeImage('next-2.png'),
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    public function test_feed_observation_payload_uses_single_observation_lookup_query_for_page(): void
    {
        $owner = User::factory()->create();

        $observations = Observation::factory()
            ->count(6)
            ->for($owner)
            ->create([
                'is_public' => true,
            ]);

        foreach ($observations as $observation) {
            $post = Post::factory()->for($owner)->create([
                'feed_key' => 'community',
                'author_kind' => 'user',
                'content' => 'Pozorovanie: ' . $observation->title,
                'meta' => [
                    'observation' => [
                        'observation_id' => (int) $observation->id,
                        'title' => (string) $observation->title,
                    ],
                ],
                'source_name' => 'observation',
                'source_uid' => 'observation-' . (int) $observation->id,
                'source_published_at' => $observation->observed_at,
                'moderation_status' => 'ok',
                'is_hidden' => false,
                'hidden_at' => null,
            ]);

            $observation->feed_post_id = (int) $post->id;
            $observation->save();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/feed?per_page=20&with=counts');
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $observationQueries = collect($queries)
            ->map(static fn (array $entry): string => (string) ($entry['query'] ?? ''))
            ->filter(static fn (string $sql): bool => preg_match('/from\s+[`"\\[]?observations[`"\\]]?/i', $sql) === 1)
            ->values();

        $this->assertLessThanOrEqual(
            1,
            $observationQueries->count(),
            'Expected batched observation loading. Queries: ' . $observationQueries->implode(' | ')
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Observation title',
            'description' => 'Observation details',
            'observed_at' => now()->subHour()->toIso8601String(),
            'location_name' => 'Bratislava',
            'images' => [$this->fakeImage()],
        ], $overrides);
    }

    private function fakeImage(string $name = 'observation.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8JvWQAAAAASUVORK5CYII=')
        );
    }

    private function fakePublicMediaDisk(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');
    }
}

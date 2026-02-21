<?php

namespace Tests\Feature;

use App\Enums\EventInviteStatus;
use App\Models\Event;
use App\Models\EventInvite;
use App\Models\Post;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_cannot_export(): void
    {
        $this->getJson('/api/me/export')->assertStatus(401);
    }

    public function test_export_returns_json_with_expected_keys(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => true,
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
        ]);

        UserPreference::query()->create([
            'user_id' => $user->id,
            'event_types' => ['moon', 'meteor_shower'],
            'interests' => ['photography'],
            'region' => 'global',
            'location_label' => 'Bratislava',
            'location_place_id' => 'place-1',
            'location_lat' => 48.1486,
            'location_lon' => 17.1077,
        ]);

        Post::factory()->for($user)->create([
            'content' => 'My export post',
            'attachment_path' => 'posts/1/photo.jpg',
            'attachment_mime' => 'image/jpeg',
        ]);

        $event = $this->createEvent('Mesiac nad mestom');
        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => User::factory()->create()->id,
            'invitee_user_id' => $user->id,
            'invitee_email' => strtolower((string) $user->email),
            'attendee_name' => 'Rajo',
            'message' => 'Pridaj sa',
            'status' => EventInviteStatus::Pending,
            'token' => 'export-invite-token',
        ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.11'])
            ->getJson('/api/me/export');

        $response->assertOk()
            ->assertJsonStructure([
                'export_version',
                'exported_at',
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                    'location',
                    'preferences',
                ],
                'newsletter' => ['subscribed', 'subscribed_at', 'frequency'],
                'posts' => [[
                    'id',
                    'body',
                    'created_at',
                    'updated_at',
                    'visibility',
                    'attachments',
                    'meta',
                ]],
                'invites' => [[
                    'id',
                    'event' => ['id', 'title', 'starts_at', 'location'],
                    'status',
                    'inviter_user_id',
                    'invitee_email',
                    'attendee_name',
                    'message',
                    'created_at',
                    'responded_at',
                ]],
                'data_summary' => ['posts_count', 'invites_count', 'invites_scope'],
            ])
            ->assertJsonPath('export_version', '1.0')
            ->assertJsonPath('newsletter.subscribed', true)
            ->assertJsonPath('posts.0.body', 'My export post')
            ->assertJsonPath('invites.0.event.id', $event->id);

        $this->assertStringContainsString(
            'attachment; filename="nebesky-sprievodca-export-',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_does_not_include_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'remember_token' => 'remember-secret',
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Safe post',
            'moderation_status' => 'blocked',
            'attachment_path' => 'posts/1/clip.mp4',
            'attachment_mime' => 'video/mp4',
        ]);

        $event = $this->createEvent('Mars opozicia');
        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => User::factory()->create()->id,
            'invitee_user_id' => $user->id,
            'invitee_email' => strtolower((string) $user->email),
            'attendee_name' => 'Rajo',
            'status' => EventInviteStatus::Pending,
            'token' => 'secret-token',
        ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.12'])
            ->getJson('/api/me/export')
            ->assertOk()
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonMissingPath('invites.0.token');

        $postMeta = $response->json('posts.0.meta');
        $this->assertIsArray($postMeta);
        $this->assertArrayNotHasKey('moderation_status', $postMeta);
        $this->assertArrayNotHasKey('attachment_path', $response->json('posts.0'));
    }

    public function test_export_only_includes_users_own_posts_and_invites(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $myPost = Post::factory()->for($me)->create(['content' => 'mine']);
        $otherPost = Post::factory()->for($other)->create(['content' => 'other']);

        $myEvent = $this->createEvent('Moja udalost');
        $otherEvent = $this->createEvent('Cudzia udalost');

        $myInvite = EventInvite::query()->create([
            'event_id' => $myEvent->id,
            'inviter_user_id' => $other->id,
            'invitee_user_id' => $me->id,
            'invitee_email' => strtolower((string) $me->email),
            'attendee_name' => 'Me',
            'status' => EventInviteStatus::Pending,
            'token' => 'mine-token',
        ]);

        $otherInvite = EventInvite::query()->create([
            'event_id' => $otherEvent->id,
            'inviter_user_id' => $me->id,
            'invitee_user_id' => $other->id,
            'invitee_email' => strtolower((string) $other->email),
            'attendee_name' => 'Other',
            'status' => EventInviteStatus::Pending,
            'token' => 'other-token',
        ]);

        Sanctum::actingAs($me);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.13'])
            ->getJson('/api/me/export');

        $response->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonCount(1, 'invites')
            ->assertJsonPath('posts.0.id', $myPost->id)
            ->assertJsonPath('invites.0.id', $myInvite->id);

        $this->assertNotContains($otherPost->id, collect($response->json('posts'))->pluck('id')->all());
        $this->assertNotContains($otherInvite->id, collect($response->json('invites'))->pluck('id')->all());
    }

    public function test_export_is_rate_limited(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ip = '10.0.0.14';
        RateLimiter::clear('me-export|' . $user->id . '|' . $ip);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/me/export')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/me/export')
            ->assertStatus(429);
    }

    private function createEvent(string $title): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(1),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('event-', true),
        ]);
    }
}

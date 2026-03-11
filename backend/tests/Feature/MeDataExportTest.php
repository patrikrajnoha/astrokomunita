<?php

namespace Tests\Feature;

use App\Enums\EventInviteStatus;
use App\Models\Event;
use App\Models\EventInvite;
use App\Models\EventReminder;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\UserPreference;
use App\Support\EventFollowTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_unauthorized_cannot_load_export_summary(): void
    {
        $this->getJson('/api/me/export/summary')->assertStatus(401);
    }

    public function test_export_returns_json_with_expected_keys(): void
    {
        $user = User::factory()->create([
            'newsletter_subscribed' => true,
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'bio' => 'Ahoj vesmir',
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
            'bortle_class' => 4,
        ]);

        UserNotificationPreference::query()->create([
            'user_id' => $user->id,
            'iss_alerts' => true,
            'good_conditions_alerts' => false,
        ]);

        $myPost = Post::factory()->for($user)->create([
            'content' => 'My export post',
            'attachment_path' => 'posts/1/photo.jpg',
            'attachment_mime' => 'image/jpeg',
        ]);

        $event = $this->createEvent('Mesiac nad mestom');
        $sentEvent = $this->createEvent('Polarna ziara');
        $followedEvent = $this->createEvent('Jupiter opozicia');

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

        EventInvite::query()->create([
            'event_id' => $sentEvent->id,
            'inviter_user_id' => $user->id,
            'invitee_user_id' => User::factory()->create()->id,
            'invitee_email' => 'other@example.com',
            'attendee_name' => 'Ina osoba',
            'message' => 'Pozyvam ta',
            'status' => EventInviteStatus::Pending,
            'token' => 'export-sent-token',
        ]);

        EventReminder::query()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'minutes_before' => 60,
            'remind_at' => now()->addHour(),
            'status' => 'pending',
            'sent_at' => null,
        ]);

        $followTable = EventFollowTable::resolve();
        $now = now();
        $followPayload = [
            'user_id' => $user->id,
            'event_id' => $followedEvent->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (EventFollowTable::supportsPersonalPlanColumns($followTable)) {
            $followPayload = array_merge($followPayload, [
                'personal_note' => 'Pozorovat z mesta',
                'reminder_at' => $now->copy()->addHours(2),
                'planned_time' => $now->copy()->addHours(3),
                'planned_location_label' => 'Bratislava',
            ]);
        }
        DB::table($followTable)->insert($followPayload);

        DB::table('post_user_bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => $myPost->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.11'])
            ->getJson('/api/me/export');

        $response->assertOk()
            ->assertJsonStructure([
                'export_version',
                'schema_version',
                'exported_at',
                'generated_by' => ['app', 'environment'],
                'checksum_sha256',
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'email_verified_at',
                    'date_of_birth',
                    'created_at',
                    'updated_at',
                    'bio',
                    'avatar_url',
                    'cover_url',
                    'location',
                    'preferences' => [
                        'event_types',
                        'interests',
                        'region',
                        'location_label',
                        'location_place_id',
                        'location_lat',
                        'location_lon',
                        'onboarding_completed_at',
                        'bortle_class',
                    ],
                ],
                'newsletter' => ['subscribed', 'subscribed_at', 'frequency'],
                'notification_preferences' => ['iss_alerts', 'good_conditions_alerts'],
                'activity' => ['last_login_at', 'posts_count', 'event_participations_count'],
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
                    'invitee_user_id',
                    'invitee_email',
                    'attendee_name',
                    'message',
                    'created_at',
                    'responded_at',
                ]],
                'invites_received' => [[
                    'id',
                    'event' => ['id', 'title', 'starts_at', 'location'],
                    'status',
                    'inviter_user_id',
                    'invitee_user_id',
                    'invitee_email',
                    'attendee_name',
                    'message',
                    'created_at',
                    'responded_at',
                ]],
                'invites_sent' => [[
                    'id',
                    'event' => ['id', 'title', 'starts_at', 'location'],
                    'status',
                    'inviter_user_id',
                    'invitee_user_id',
                    'invitee_email',
                    'attendee_name',
                    'message',
                    'created_at',
                    'responded_at',
                ]],
                'reminders' => [[
                    'id',
                    'event' => ['id', 'title', 'starts_at'],
                    'minutes_before',
                    'remind_at',
                    'status',
                    'sent_at',
                    'created_at',
                    'updated_at',
                ]],
                'followed_events' => [[
                    'event' => ['id', 'title', 'type', 'starts_at', 'ends_at', 'source_name', 'source_uid'],
                    'followed_at',
                    'personal_plan' => ['note', 'reminder_at', 'planned_time', 'planned_location_label'],
                ]],
                'bookmarks' => [[
                    'post_id',
                    'bookmarked_at',
                    'post' => ['id', 'body_excerpt', 'created_at', 'visibility'],
                ]],
                'sections',
                'data_summary' => ['posts_count', 'invites_count', 'invites_scope'],
            ])
            ->assertJsonPath('export_version', '2.0')
            ->assertJsonPath('schema_version', '2.0')
            ->assertJsonPath('newsletter.subscribed', true)
            ->assertJsonPath('notification_preferences.iss_alerts', true)
            ->assertJsonPath('posts.0.body', 'My export post')
            ->assertJsonPath('invites.0.event.id', $event->id)
            ->assertJsonPath('invites_received.0.event.id', $event->id)
            ->assertJsonPath('invites_sent.0.event.id', $sentEvent->id)
            ->assertJsonPath('data_summary.reminders_count', 1)
            ->assertJsonPath('data_summary.bookmarks_count', 1)
            ->assertJsonPath('data_summary.followed_events_count', 1)
            ->assertJsonPath('data_summary.attachments_count', 1);

        $this->assertNotNull($response->json('checksum_sha256'));

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
            ->assertJsonCount(1, 'invites_received')
            ->assertJsonCount(1, 'invites_sent')
            ->assertJsonPath('posts.0.id', $myPost->id)
            ->assertJsonPath('invites.0.id', $myInvite->id)
            ->assertJsonPath('invites_received.0.id', $myInvite->id)
            ->assertJsonPath('invites_sent.0.id', $otherInvite->id);

        $this->assertNotContains($otherPost->id, collect($response->json('posts'))->pluck('id')->all());
        $this->assertNotContains($otherInvite->id, collect($response->json('invites'))->pluck('id')->all());
    }

    public function test_export_summary_returns_counts_and_estimate(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Post::factory()->for($user)->create(['content' => 'mine']);
        Post::factory()->for($other)->create(['content' => 'other']);

        $receivedEvent = $this->createEvent('Prijata');
        $sentEvent = $this->createEvent('Odoslana');
        $followedEvent = $this->createEvent('Sledovana');

        EventInvite::query()->create([
            'event_id' => $receivedEvent->id,
            'inviter_user_id' => $other->id,
            'invitee_user_id' => $user->id,
            'invitee_email' => strtolower((string) $user->email),
            'attendee_name' => 'Me',
            'status' => EventInviteStatus::Pending,
            'token' => 'mine-token',
        ]);

        EventInvite::query()->create([
            'event_id' => $sentEvent->id,
            'inviter_user_id' => $user->id,
            'invitee_user_id' => $other->id,
            'invitee_email' => strtolower((string) $other->email),
            'attendee_name' => 'Other',
            'status' => EventInviteStatus::Pending,
            'token' => 'other-token',
        ]);

        EventReminder::query()->create([
            'user_id' => $user->id,
            'event_id' => $receivedEvent->id,
            'minutes_before' => 15,
            'remind_at' => now()->addMinutes(15),
            'status' => 'pending',
        ]);

        $followTable = EventFollowTable::resolve();
        DB::table($followTable)->insert([
            'user_id' => $user->id,
            'event_id' => $followedEvent->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('post_user_bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => Post::query()->where('user_id', $user->id)->value('id'),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/me/export/summary')
            ->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'schema_version',
                'estimated_bytes',
                'counts' => [
                    'posts_count',
                    'invites_received_count',
                    'invites_sent_count',
                    'reminders_count',
                    'followed_events_count',
                    'bookmarks_count',
                    'attachments_count',
                ],
                'section_counts',
                'sections',
            ])
            ->assertJsonPath('schema_version', '2.0')
            ->assertJsonPath('counts.posts_count', 1)
            ->assertJsonPath('counts.invites_received_count', 1)
            ->assertJsonPath('counts.invites_sent_count', 1)
            ->assertJsonPath('counts.reminders_count', 1)
            ->assertJsonPath('counts.followed_events_count', 1)
            ->assertJsonPath('counts.bookmarks_count', 1)
            ->assertJsonPath('counts.attachments_count', 0);
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

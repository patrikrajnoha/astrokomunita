<?php

namespace Tests\Feature;

use App\Enums\EventInviteStatus;
use App\Models\Event;
use App\Models\EventInvite;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeActivityEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_requires_auth(): void
    {
        $this->getJson('/api/me/activity')->assertStatus(401);
    }

    public function test_activity_returns_counts_and_last_login(): void
    {
        $user = User::factory()->create([
            'email' => 'astro@example.com',
            'last_login_at' => '2026-02-21 17:00:00',
        ]);
        $other = User::factory()->create();

        Post::factory()->for($user)->create();
        Post::factory()->for($user)->create();
        Post::factory()->for($other)->create();

        $event = $this->createEvent('Pozorovanie Mesiaca');

        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $other->id,
            'invitee_user_id' => $user->id,
            'invitee_email' => strtolower((string) $user->email),
            'attendee_name' => 'Rajo',
            'status' => EventInviteStatus::Accepted,
            'token' => 'accepted-1',
        ]);

        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $other->id,
            'invitee_user_id' => null,
            'invitee_email' => strtoupper((string) $user->email),
            'attendee_name' => 'Rajo',
            'status' => EventInviteStatus::Accepted,
            'token' => 'accepted-2',
        ]);

        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $other->id,
            'invitee_user_id' => $user->id,
            'invitee_email' => strtolower((string) $user->email),
            'attendee_name' => 'Rajo',
            'status' => EventInviteStatus::Declined,
            'token' => 'declined',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/activity');

        $response
            ->assertOk()
            ->assertJsonPath('posts_count', 2)
            ->assertJsonPath('event_participations_count', 2)
            ->assertJsonPath('last_login_at', '2026-02-21T17:00:00+00:00');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('activity.posts_count', 2)
            ->assertJsonPath('activity.event_participations_count', 2)
            ->assertJsonPath('activity.last_login_at', '2026-02-21T17:00:00+00:00');
    }

    private function createEvent(string $title): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('event-', true),
        ]);
    }
}

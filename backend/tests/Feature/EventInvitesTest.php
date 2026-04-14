<?php

namespace Tests\Feature;

use App\Enums\EventInviteStatus;
use App\Events\NotificationCreated;
use App\Models\Event;
use App\Models\EventInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventInvitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviter_can_create_invite_invalid_data_returns_422_and_guest_is_unauthorized(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $event = $this->createEvent();

        $this->postJson("/api/events/{$event->id}/invites", [
            'invitee_user_id' => $invitee->id,
            'attendee_name' => 'Test',
        ])->assertStatus(401);

        Sanctum::actingAs($inviter);

        $this->postJson("/api/events/{$event->id}/invites", [
            'invitee_user_id' => $invitee->id,
            'attendee_name' => '',
        ])->assertStatus(422);

        $response = $this->postJson("/api/events/{$event->id}/invites", [
            'invitee_user_id' => $invitee->id,
            'attendee_name' => 'Marek Hvezdar',
            'message' => 'Vezmi kamarata.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.event_id', $event->id)
            ->assertJsonPath('data.inviter_user_id', $inviter->id)
            ->assertJsonPath('data.invitee_user_id', $invitee->id)
            ->assertJsonPath('data.attendee_name', 'Marek Hvezdar')
            ->assertJsonPath('data.status', EventInviteStatus::Pending->value)
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('event_invites', [
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'attendee_name' => 'Marek Hvezdar',
            'status' => EventInviteStatus::Pending->value,
        ]);
    }

    public function test_invitee_can_list_their_invites(): void
    {
        $invitee = User::factory()->create();
        $other = User::factory()->create();
        $inviter = User::factory()->create();

        $firstEvent = $this->createEvent('Mesiac a planety');
        $secondEvent = $this->createEvent('Skryta udalost');

        EventInvite::query()->create([
            'event_id' => $firstEvent->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'invitee_email' => strtolower((string) $invitee->email),
            'attendee_name' => 'Juraj',
            'status' => EventInviteStatus::Pending,
            'token' => 'invite-token-1',
        ]);

        EventInvite::query()->create([
            'event_id' => $secondEvent->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $other->id,
            'invitee_email' => strtolower((string) $other->email),
            'attendee_name' => 'Peter',
            'status' => EventInviteStatus::Pending,
            'token' => 'invite-token-2',
        ]);

        Sanctum::actingAs($invitee);

        $response = $this->getJson('/api/me/invites');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.id', $firstEvent->id)
            ->assertJsonPath('data.0.attendee_name', 'Juraj');
    }

    public function test_invitee_can_accept_and_decline_which_updates_status_and_responded_at(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $event = $this->createEvent();

        $acceptInvite = EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'invitee_email' => strtolower((string) $invitee->email),
            'attendee_name' => 'Adam',
            'status' => EventInviteStatus::Pending,
            'token' => 'accept-token',
        ]);

        $declineInvite = EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'invitee_email' => strtolower((string) $invitee->email),
            'attendee_name' => 'Adam',
            'status' => EventInviteStatus::Pending,
            'token' => 'decline-token',
        ]);

        Sanctum::actingAs($invitee);

        $this->postJson("/api/invites/{$acceptInvite->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', EventInviteStatus::Accepted->value);

        $this->postJson("/api/invites/{$declineInvite->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', EventInviteStatus::Declined->value);

        $this->assertDatabaseHas('event_invites', [
            'id' => $acceptInvite->id,
            'status' => EventInviteStatus::Accepted->value,
        ]);
        $this->assertDatabaseHas('event_invites', [
            'id' => $declineInvite->id,
            'status' => EventInviteStatus::Declined->value,
        ]);

        $this->assertNotNull(EventInvite::query()->findOrFail($acceptInvite->id)->responded_at);
        $this->assertNotNull(EventInvite::query()->findOrFail($declineInvite->id)->responded_at);
    }

    public function test_other_user_cannot_accept_or_decline_foreign_invite(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $event = $this->createEvent();

        $invite = EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'invitee_email' => strtolower((string) $invitee->email),
            'attendee_name' => 'Niekto',
            'status' => EventInviteStatus::Pending,
            'token' => 'foreign-invite-token',
        ]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/invites/{$invite->id}/accept")->assertStatus(403);
        $this->postJson("/api/invites/{$invite->id}/decline")->assertStatus(403);
    }

    public function test_realtime_notification_is_dispatched_on_create_and_response(): void
    {
        EventFacade::fake([NotificationCreated::class]);

        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $event = $this->createEvent();

        Sanctum::actingAs($inviter);
        $createResponse = $this->postJson("/api/events/{$event->id}/invites", [
            'invitee_user_id' => $invitee->id,
            'attendee_name' => 'Nora',
        ]);
        $createResponse->assertOk();

        $inviteId = (int) $createResponse->json('data.id');
        Sanctum::actingAs($invitee);
        $this->postJson("/api/invites/{$inviteId}/accept")->assertOk();

        EventFacade::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($invitee) {
            $channels = $event->broadcastOn();
            $payload = $event->broadcastWith();

            return ($channels[0]->name ?? null) === 'private-users.' . $invitee->id
                && ($payload['notification']['type'] ?? null) === 'event_invite';
        });

        EventFacade::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($inviter) {
            $channels = $event->broadcastOn();
            $payload = $event->broadcastWith();

            return ($channels[0]->name ?? null) === 'private-users.' . $inviter->id
                && ($payload['notification']['type'] ?? null) === 'event_invite_response'
                && ($payload['notification']['data']['response_status'] ?? null) === EventInviteStatus::Accepted->value;
        });
    }

    public function test_public_token_endpoint_returns_read_only_payload_for_pending_and_accepted_statuses(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $event = $this->createEvent();

        $invite = EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => $invitee->id,
            'invitee_email' => strtolower((string) $invitee->email),
            'attendee_name' => 'Lucia',
            'status' => EventInviteStatus::Pending,
            'token' => 'public-token-xyz',
            'token_expires_at' => now()->addDays(3),
        ]);

        $this->getJson('/api/invites/public/public-token-xyz')
            ->assertOk()
            ->assertJsonPath('data.id', $invite->id)
            ->assertJsonPath('data.status', EventInviteStatus::Pending->value)
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonMissingPath('data.invitee_email')
            ->assertJsonMissingPath('data.token');

        $invite->status = EventInviteStatus::Accepted;
        $invite->responded_at = now();
        $invite->save();

        $this->getJson('/api/invites/public/public-token-xyz')
            ->assertOk()
            ->assertJsonPath('data.status', EventInviteStatus::Accepted->value)
            ->assertJsonMissingPath('data.invitee_email')
            ->assertJsonMissingPath('data.token');
    }

    public function test_public_token_endpoint_returns_404_for_expired_token(): void
    {
        $inviter = User::factory()->create();
        $event = $this->createEvent('Expired invite event');

        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => null,
            'invitee_email' => 'invitee@example.com',
            'attendee_name' => 'Nina',
            'status' => EventInviteStatus::Pending,
            'token' => 'expired-public-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/invites/public/expired-public-token')
            ->assertNotFound();
    }

    public function test_public_token_endpoint_redirects_browser_requests_to_frontend_route(): void
    {
        $inviter = User::factory()->create();
        $event = $this->createEvent('Invite redirect event');

        EventInvite::query()->create([
            'event_id' => $event->id,
            'inviter_user_id' => $inviter->id,
            'invitee_user_id' => null,
            'invitee_email' => 'invitee@example.com',
            'attendee_name' => 'Nina',
            'status' => EventInviteStatus::Pending,
            'token' => 'redirect-public-token',
            'token_expires_at' => now()->addDay(),
        ]);

        $this->get('/api/invites/public/redirect-public-token')
            ->assertRedirect(rtrim((string) env('FRONTEND_URL', config('app.url', 'http://localhost')), '/') . '/invites/public/redirect-public-token');
    }

    private function createEvent(string $title = 'Meteor shower'): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(2),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => uniqid('manual-', true),
        ]);
    }
}

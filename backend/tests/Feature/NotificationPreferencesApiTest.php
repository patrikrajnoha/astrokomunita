<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_defaults_for_new_user_and_persists_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notification-preferences');

        $response->assertOk()
            ->assertJson([
                'email_enabled' => false,
                'in_app' => [
                    'post_like' => true,
                    'post_comment' => true,
                    'reply' => true,
                    'event_reminder' => true,
                    'event_reminder_meteors' => true,
                    'event_reminder_eclipses' => true,
                    'event_reminder_planetary' => true,
                    'event_reminder_small_bodies' => true,
                    'event_reminder_aurora' => true,
                    'event_reminder_space' => true,
                    'event_reminder_observing' => true,
                    'system' => true,
                ],
                'email' => [
                    'post_like' => false,
                    'post_comment' => false,
                    'reply' => false,
                    'event_reminder' => false,
                    'event_reminder_meteors' => false,
                    'event_reminder_eclipses' => false,
                    'event_reminder_planetary' => false,
                    'event_reminder_small_bodies' => false,
                    'event_reminder_aurora' => false,
                    'event_reminder_space' => false,
                    'event_reminder_observing' => false,
                    'system' => false,
                ],
            ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'email_enabled' => false,
        ]);
    }

    public function test_put_updates_preferences_and_persists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'in_app' => [
                'post_like' => false,
                'post_comment' => true,
                'reply' => false,
                'event_reminder' => true,
                'event_reminder_meteors' => true,
                'event_reminder_eclipses' => false,
                'event_reminder_planetary' => true,
                'event_reminder_small_bodies' => false,
                'event_reminder_aurora' => true,
                'event_reminder_space' => false,
                'event_reminder_observing' => true,
                'system' => true,
            ],
            'email_enabled' => true,
            'email' => [
                'post_like' => true,
                'post_comment' => false,
                'reply' => false,
                'event_reminder' => true,
                'event_reminder_meteors' => true,
                'event_reminder_eclipses' => true,
                'event_reminder_planetary' => false,
                'event_reminder_small_bodies' => false,
                'event_reminder_aurora' => false,
                'event_reminder_space' => true,
                'event_reminder_observing' => false,
                'system' => false,
            ],
        ];

        $response = $this->putJson('/api/notification-preferences', $payload);
        $response->assertOk()->assertJson($payload);

        $preference = NotificationPreference::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($preference);
        $this->assertSame($payload['in_app'], $preference->in_app_json);
        $this->assertSame($payload['email'], $preference->email_json);
        $this->assertTrue((bool) $preference->email_enabled);
    }

    public function test_guest_is_blocked_with_401(): void
    {
        $payload = [
            'in_app' => [
                'post_like' => true,
                'post_comment' => true,
                'reply' => true,
                'event_reminder' => true,
                'event_reminder_meteors' => true,
                'event_reminder_eclipses' => true,
                'event_reminder_planetary' => true,
                'event_reminder_small_bodies' => true,
                'event_reminder_aurora' => true,
                'event_reminder_space' => true,
                'event_reminder_observing' => true,
                'system' => true,
            ],
            'email_enabled' => false,
        ];

        $this->getJson('/api/notification-preferences')->assertStatus(401);
        $this->putJson('/api/notification-preferences', $payload)->assertStatus(401);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserNotificationPreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_defaults_for_new_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/notifications/preferences');

        $response->assertOk()->assertJson([
            'iss_alerts' => false,
            'good_conditions_alerts' => false,
        ]);
    }

    public function test_post_updates_preferences_and_persists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'iss_alerts' => true,
            'good_conditions_alerts' => true,
        ];

        $response = $this->postJson('/api/me/notifications/preferences', $payload);
        $response->assertOk()->assertJson($payload);

        /** @var UserNotificationPreference|null $record */
        $record = UserNotificationPreference::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->iss_alerts);
        $this->assertTrue((bool) $record->good_conditions_alerts);
    }

    public function test_guest_is_blocked_with_401(): void
    {
        $this->getJson('/api/me/notifications/preferences')->assertStatus(401);
        $this->postJson('/api/me/notifications/preferences', [
            'iss_alerts' => true,
            'good_conditions_alerts' => false,
        ])->assertStatus(401);
    }

    public function test_get_returns_defaults_with_unavailable_reason_when_table_is_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Schema::dropIfExists('user_notification_preferences');

        try {
            $this->getJson('/api/me/notifications/preferences')
                ->assertOk()
                ->assertJson([
                    'iss_alerts' => false,
                    'good_conditions_alerts' => false,
                    'reason' => 'unavailable',
                ]);
        } finally {
            $this->recreatePreferencesTable();
        }
    }

    public function test_post_returns_defaults_with_unavailable_reason_when_table_is_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Schema::dropIfExists('user_notification_preferences');

        try {
            $this->postJson('/api/me/notifications/preferences', [
                'iss_alerts' => true,
                'good_conditions_alerts' => true,
            ])->assertOk()->assertJson([
                'iss_alerts' => false,
                'good_conditions_alerts' => false,
                'reason' => 'unavailable',
            ]);
        } finally {
            $this->recreatePreferencesTable();
        }
    }

    private function recreatePreferencesTable(): void
    {
        if (Schema::hasTable('user_notification_preferences')) {
            return;
        }

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('iss_alerts')->default(false);
            $table->boolean('good_conditions_alerts')->default(false);
            $table->timestamps();
        });
    }
}

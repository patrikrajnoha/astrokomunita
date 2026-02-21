<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\EmailVerificationSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_and_update_auth_settings(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/auth-settings')
            ->assertOk()
            ->assertJsonPath('data.require_email_verification', true);

        $this->patchJson('/api/admin/auth-settings', [
            'require_email_verification' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.require_email_verification', false);

        $this->assertFalse(app(EmailVerificationSettingService::class)->requiresEmailVerification());
    }

    public function test_non_admin_cannot_access_auth_settings_endpoint(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'role' => 'user',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/admin/auth-settings', [
            'require_email_verification' => false,
        ])->assertStatus(403);
    }
}

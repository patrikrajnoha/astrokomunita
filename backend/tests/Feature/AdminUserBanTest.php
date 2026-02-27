<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserBanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_ban_user_with_reason_via_patch_endpoint(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$target->id}/ban", [
            'reason' => 'Repeated hate speech in comments.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('id', $target->id)
            ->assertJsonPath('is_banned', true)
            ->assertJsonPath('ban_reason', 'Repeated hate speech in comments.');

        $target->refresh();
        $this->assertTrue((bool) $target->is_banned);
        $this->assertNotNull($target->banned_at);
        $this->assertSame('Repeated hate speech in comments.', $target->ban_reason);
    }

    public function test_ban_requires_reason(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$target->id}/ban", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_banned_at_blocks_protected_endpoints_even_when_legacy_flag_is_false(): void
    {
        $user = User::factory()->create([
            'is_banned' => false,
            'banned_at' => now(),
            'ban_reason' => 'Abusive behavior.',
        ]);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_BANNED')
            ->assertJsonPath('reason', 'Abusive behavior.');

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_BANNED')
            ->assertJsonPath('reason', 'Abusive behavior.');
    }
}

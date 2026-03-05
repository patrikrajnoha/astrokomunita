<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleAndBotGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_and_revoke_editor_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_admin' => false,
            'is_bot' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_EDITOR])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_EDITOR);

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_USER])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_USER);
    }

    public function test_non_admin_cannot_change_roles(): void
    {
        $editor = User::factory()->editor()->create();
        $target = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_admin' => false,
            'is_bot' => false,
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_EDITOR])
            ->assertForbidden();
    }

    public function test_editor_is_blocked_from_admin_community_endpoints(): void
    {
        $editor = User::factory()->editor()->create();

        Sanctum::actingAs($editor);

        $this->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_only_admin_can_update_bot_profile_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'kozmobot',
            'name' => 'Kozmo',
        ]);

        Sanctum::actingAs($editor);
        $this->patchJson("/api/admin/users/{$bot->id}/profile", [
            'name' => 'Edited by editor',
            'bio' => 'editor attempt',
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/users/{$bot->id}/profile", [
            'name' => 'Edited by admin',
            'bio' => 'admin update',
            'avatar_path' => 'avatars/bot.png',
            'cover_path' => 'covers/bot.png',
        ])->assertOk()
            ->assertJsonPath('name', 'Edited by admin')
            ->assertJsonPath('bio', 'admin update');
    }

    public function test_bot_role_email_is_forced_to_null(): void
    {
        $bot = User::factory()->create([
            'role' => User::ROLE_BOT,
            'is_bot' => true,
            'email' => 'bot@example.com',
        ]);

        $this->assertNull($bot->fresh()->email);

        $bot->forceFill(['email' => 'still-not-allowed@example.com'])->save();
        $this->assertNull($bot->fresh()->email);
    }

    public function test_default_user_seeding_keeps_only_kozmobot_and_stellarbot_as_bots(): void
    {
        app(DefaultUsersSeeder::class)->seed();

        $botUsernames = User::query()
            ->where('is_bot', true)
            ->orderBy('username')
            ->pluck('username')
            ->all();

        $this->assertSame(['kozmobot', 'stellarbot'], $botUsernames);
        $this->assertNull(User::query()->where('username', 'astrobot')->first());
    }
}

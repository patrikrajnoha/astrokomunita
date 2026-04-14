<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleAndBotGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_role_for_regular_non_bot_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_admin' => false,
            'is_bot' => false,
        ]);

        Sanctum::actingAs($admin);
        Cache::put('admin:stats:v1', ['stale' => true], now()->addMinute());

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_EDITOR])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_EDITOR);
        $this->assertNull(Cache::get('admin:stats:v1'));

        Cache::put('admin:stats:v1', ['stale' => true], now()->addMinute());

        $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => User::ROLE_USER])
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_USER);
        $this->assertNull(Cache::get('admin:stats:v1'));
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

    public function test_non_admin_cannot_ban_deactivate_or_reset_accounts(): void
    {
        $editor = User::factory()->editor()->create();
        $target = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_admin' => false,
            'is_bot' => false,
            'is_active' => true,
            'is_banned' => false,
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/admin/users/{$target->id}/ban", ['reason' => 'Not allowed'])
            ->assertForbidden();
        $this->postJson("/api/admin/users/{$target->id}/deactivate")
            ->assertForbidden();
        $this->postJson("/api/admin/users/{$target->id}/reset-profile")
            ->assertForbidden();
    }

    public function test_editor_is_blocked_from_admin_community_endpoints(): void
    {
        $editor = User::factory()->editor()->create();

        Sanctum::actingAs($editor);

        $this->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_admin_users_index_can_exclude_bot_accounts_via_include_bots_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $communityUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_bot' => false,
        ]);
        $botUser = User::factory()->bot()->create([
            'username' => 'kozmobot',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?include_bots=0');
        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($communityUser->id, $returnedIds);
        $this->assertNotContains($botUser->id, $returnedIds);
    }

    public function test_admin_users_index_applies_role_and_status_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create([
            'is_bot' => false,
            'is_active' => true,
            'is_banned' => false,
        ]);
        $bannedEditor = User::factory()->editor()->create([
            'is_bot' => false,
            'is_active' => true,
            'is_banned' => true,
        ]);
        $inactiveEditor = User::factory()->editor()->create([
            'is_bot' => false,
            'is_active' => false,
            'is_banned' => false,
        ]);

        Sanctum::actingAs($admin);

        $activeEditors = $this->getJson('/api/admin/users?include_bots=0&role=editor&status=active');
        $activeEditors->assertOk();
        $activeIds = collect($activeEditors->json('data'))->pluck('id')->all();
        $this->assertContains($editor->id, $activeIds);
        $this->assertNotContains($bannedEditor->id, $activeIds);
        $this->assertNotContains($inactiveEditor->id, $activeIds);

        $bannedEditors = $this->getJson('/api/admin/users?include_bots=0&role=editor&status=banned');
        $bannedEditors->assertOk();
        $bannedIds = collect($bannedEditors->json('data'))->pluck('id')->all();
        $this->assertContains($bannedEditor->id, $bannedIds);
        $this->assertNotContains($editor->id, $bannedIds);
        $this->assertNotContains($inactiveEditor->id, $bannedIds);
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
            'name' => 'Edited by staff',
            'bio' => 'admin update',
        ])->assertOk()
            ->assertJsonPath('name', 'Edited by staff')
            ->assertJsonPath('bio', 'admin update');
    }

    public function test_admin_bot_profile_update_rejects_blocked_name(): void
    {
        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'name' => 'Kozmo',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$bot->id}/profile", [
            'name' => 'Pica bot',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_bot_profile_update_rejects_name_containing_astrokomunita_keyword(): void
    {
        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'name' => 'Kozmo',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$bot->id}/profile", [
            'name' => 'Astrokomunita oficial',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_bot_profile_rejects_raw_avatar_and_cover_path_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'stellarbot',
            'name' => 'Stellar Bot',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$bot->id}/profile", [
            'avatar_path' => 'avatars/bot.png',
            'cover_path' => 'covers/bot.png',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_path', 'cover_path']);
    }

    public function test_regular_profile_update_is_forbidden_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create([
            'is_bot' => false,
            'role' => User::ROLE_USER,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$regularUser->id}/profile", [
            'avatar_path' => 'avatars/user.png',
            'cover_path' => 'covers/user.png',
        ])->assertForbidden();
    }

    public function test_admin_can_manage_bot_avatar_preferences_and_remove_media(): void
    {
        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'stellarbot',
            'avatar_path' => 'avatars/1/current.png',
            'cover_path' => 'covers/1/current.png',
            'avatar_mode' => 'image',
            'avatar_color' => null,
            'avatar_icon' => null,
            'avatar_seed' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$bot->id}/avatar/preferences", [
            'avatar_mode' => 'image',
            'avatar_path' => 'bots/stellarbot/sb_red.png',
        ])->assertOk()
            ->assertJsonPath('avatar_mode', 'image')
            ->assertJsonPath('avatar_path', 'bots/stellarbot/sb_red.png')
            ->assertJsonPath('avatar_color', null)
            ->assertJsonPath('avatar_icon', null)
            ->assertJsonPath('avatar_seed', null);

        $this->deleteJson("/api/admin/users/{$bot->id}/avatar")
            ->assertOk()
            ->assertJsonPath('avatar_path', 'bots/stellarbot/sb_blue.png')
            ->assertJsonPath('avatar_mode', 'image');

        $this->deleteJson("/api/admin/users/{$bot->id}/cover")
            ->assertOk()
            ->assertJsonPath('cover_path', null);

        $this->patchJson("/api/admin/users/{$bot->id}/avatar/preferences", [
            'avatar_mode' => 'generated',
            'avatar_path' => 'bots/stellarbot/sb_green.png',
        ])->assertOk()
            ->assertJsonPath('avatar_mode', 'image')
            ->assertJsonPath('avatar_path', 'bots/stellarbot/sb_green.png');
    }

    public function test_admin_write_endpoints_for_deactivate_and_reset_are_allowed_for_non_admin_targets(): void
    {
        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'is_active' => true,
            'bio' => 'bot bio',
        ]);
        $regular = User::factory()->create([
            'is_bot' => false,
            'role' => User::ROLE_USER,
            'is_active' => true,
            'bio' => 'regular bio',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$regular->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->postJson("/api/admin/users/{$regular->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->postJson("/api/admin/users/{$regular->id}/reset-profile")
            ->assertOk()
            ->assertJsonPath('bio', null)
            ->assertJsonPath('avatar_path', null)
            ->assertJsonPath('cover_path', null);

        $this->postJson("/api/admin/users/{$bot->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->postJson("/api/admin/users/{$bot->id}/reset-profile")
            ->assertOk()
            ->assertJsonPath('bio', null)
            ->assertJsonPath('avatar_path', null)
            ->assertJsonPath('cover_path', null);
    }

    public function test_admin_cannot_change_role_or_account_state_for_admin_targets_or_self(): void
    {
        $admin = User::factory()->admin()->create();
        $adminTarget = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$adminTarget->id}/role", ['role' => User::ROLE_EDITOR])
            ->assertForbidden();
        $this->patchJson("/api/admin/users/{$admin->id}/role", ['role' => User::ROLE_EDITOR])
            ->assertForbidden();

        $this->patchJson("/api/admin/users/{$adminTarget->id}/ban", ['reason' => 'Forbidden'])
            ->assertForbidden();
        $this->patchJson("/api/admin/users/{$admin->id}/ban", ['reason' => 'Forbidden'])
            ->assertForbidden();

        $this->postJson("/api/admin/users/{$adminTarget->id}/deactivate")
            ->assertForbidden();
        $this->postJson("/api/admin/users/{$admin->id}/deactivate")
            ->assertForbidden();
    }

    public function test_non_admin_cannot_manage_bot_media_endpoints(): void
    {
        $editor = User::factory()->editor()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'kozmobot',
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/admin/users/{$bot->id}/avatar/preferences", [
            'avatar_mode' => 'generated',
            'avatar_color' => 2,
            'avatar_icon' => 1,
            'avatar_seed' => 'seed',
        ])->assertForbidden();

        $this->deleteJson("/api/admin/users/{$bot->id}/avatar")->assertForbidden();
        $this->deleteJson("/api/admin/users/{$bot->id}/cover")->assertForbidden();
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

    public function test_bot_login_is_rejected_even_when_legacy_bot_has_email(): void
    {
        DB::table('users')->insert([
            'name' => 'Legacy Bot',
            'username' => 'legacybot',
            'email' => 'legacybot@example.test',
            'password' => Hash::make('secret-pass'),
            'role' => User::ROLE_BOT,
            'is_bot' => true,
            'is_admin' => false,
            'is_active' => true,
            'is_banned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'legacybot@example.test',
            'password' => 'secret-pass',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nesprávny e-mail alebo heslo.');
    }

    public function test_legacy_astrobot_feed_endpoint_does_not_exist(): void
    {
        $this->getJson('/api/feed/astrobot')->assertNotFound();
    }
}

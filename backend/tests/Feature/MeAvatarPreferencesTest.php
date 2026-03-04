<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeAvatarPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_preferences_are_saved_and_returned_via_me_endpoints(): void
    {
        $user = User::factory()->create([
            'avatar_mode' => 'image',
            'avatar_color' => null,
            'avatar_icon' => null,
            'avatar_seed' => null,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/me/avatar', [
                'avatar_mode' => 'generated',
                'avatar_color' => '#73df84',
                'avatar_icon' => 'moon',
                'avatar_seed' => 'rnd-123',
            ])
            ->assertOk()
            ->assertJsonPath('avatar_mode', 'generated')
            ->assertJsonPath('avatar_color', 2)
            ->assertJsonPath('avatar_icon', 4)
            ->assertJsonPath('avatar_seed', 'rnd-123');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('avatar_mode', 'generated')
            ->assertJsonPath('avatar_color', 2)
            ->assertJsonPath('avatar_icon', 4)
            ->assertJsonPath('avatar_seed', 'rnd-123');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avatar_mode', 'generated')
            ->assertJsonPath('avatar_color', 2)
            ->assertJsonPath('avatar_icon', 4)
            ->assertJsonPath('avatar_seed', 'rnd-123');
    }

    public function test_avatar_preferences_validate_allowlists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me/avatar', [
                'avatar_mode' => 'invalid',
                'avatar_color' => 0,
                'avatar_icon' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_mode']);

        $this->actingAs($user)
            ->patchJson('/api/me/avatar', [
                'avatar_mode' => 'generated',
                'avatar_color' => 99,
                'avatar_icon' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_color']);

        $this->actingAs($user)
            ->patchJson('/api/me/avatar', [
                'avatar_mode' => 'generated',
                'avatar_color' => 1,
                'avatar_icon' => 'unknown-icon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_icon']);
    }

    public function test_avatar_image_can_be_removed_without_changing_mode(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);

        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/11/test-avatar.png',
            'avatar_mode' => 'image',
        ]);

        Storage::disk('public')->put('avatars/11/test-avatar.png', 'avatar');

        $this->actingAs($user)
            ->deleteJson('/api/me/avatar-image')
            ->assertOk()
            ->assertJsonPath('avatar_path', null)
            ->assertJsonPath('avatar_mode', 'image');

        Storage::disk('public')->assertMissing('avatars/11/test-avatar.png');
    }

    public function test_switching_to_generated_mode_removes_uploaded_avatar_image(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);

        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/22/test-avatar.png',
            'avatar_mode' => 'image',
        ]);

        Storage::disk('public')->put('avatars/22/test-avatar.png', 'avatar');

        $this->actingAs($user)
            ->patchJson('/api/me/avatar', [
                'avatar_mode' => 'generated',
                'avatar_color' => 1,
                'avatar_icon' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('avatar_mode', 'generated')
            ->assertJsonPath('avatar_path', null)
            ->assertJsonPath('avatar_url', null);

        Storage::disk('public')->assertMissing('avatars/22/test-avatar.png');
    }
}

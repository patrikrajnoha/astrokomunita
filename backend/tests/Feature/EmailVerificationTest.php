<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_link_email_verification_flag_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('auth.enable_signed_link_email_verification'));
    }

    public function test_unverified_user_is_blocked_from_posting(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', [
            'content' => 'Unverified should not post.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('action', 'GO_TO_SETTINGS_EMAIL');
    }

    public function test_verified_user_can_post(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Verified user post.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Verified user post.',
        ]);
    }

    public function test_legacy_resend_verification_endpoint_is_deprecated_by_default(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(410)
            ->assertJsonPath('error_code', 'EMAIL_VERIFY_DEPRECATED')
            ->assertJsonPath('action', 'GO_TO_SETTINGS_EMAIL');

        Notification::assertNothingSent();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_legacy_resend_endpoint_returns_identical_deprecated_shape_for_guest_and_authenticated_user(): void
    {
        $first = $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(410)
            ->json();

        $unverified = User::factory()->unverified()->create();
        Sanctum::actingAs($unverified);

        $second = $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(410)
            ->json();

        $this->assertSame($first, $second);
        $this->assertSame('EMAIL_VERIFY_DEPRECATED', data_get($first, 'error_code'));
        $this->assertSame('GO_TO_SETTINGS_EMAIL', data_get($first, 'action'));
    }

    public function test_signed_link_verify_is_fail_fast_and_never_touches_database(): void
    {
        $existingUser = User::factory()->unverified()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $first = $this->getJson('/api/auth/verify-email/'.$existingUser->id.'/invalid-hash')
            ->assertStatus(410)
            ->json();

        $second = $this->getJson('/api/auth/verify-email/999999/another-hash')
            ->assertStatus(410)
            ->json();

        $this->assertSame($first, $second);
        $this->assertSame('EMAIL_VERIFY_DEPRECATED', data_get($first, 'error_code'));
        $this->assertSame('GO_TO_SETTINGS_EMAIL', data_get($first, 'action'));

        $usersQueries = collect(DB::getQueryLog())
            ->filter(function (array $entry): bool {
                $sql = strtolower((string) ($entry['query'] ?? ''));
                return str_contains($sql, 'from "users"')
                    || str_contains($sql, 'from `users`')
                    || str_contains($sql, 'from users');
            });
        $this->assertCount(0, $usersQueries, 'Legacy verify endpoint must not query users table in default mode.');

        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    public function test_legacy_verify_email_endpoint_is_deprecated_by_default(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->getJson($url)
            ->assertStatus(410)
            ->assertJsonPath('error_code', 'EMAIL_VERIFY_DEPRECATED')
            ->assertJsonPath('action', 'GO_TO_SETTINGS_EMAIL');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_legacy_signed_link_verification_can_be_reenabled_with_config_flag(): void
    {
        config()->set('auth.enable_signed_link_email_verification', true);

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}

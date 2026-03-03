<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationMail;
use App\Models\EmailChangeRequest;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_verification_code_creates_hashed_token(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/account/email/verification/send')
            ->assertOk()
            ->assertJsonPath('message', 'Verification code sent.');

        $verification = EmailVerification::query()->firstOrFail();
        $this->assertSame($user->id, $verification->user_id);
        $this->assertSame(EmailVerification::PURPOSE_ACCOUNT_VERIFICATION, $verification->purpose);
        $this->assertTrue(str_starts_with((string) $verification->code_hash, '$'));
        $this->assertNotNull($verification->expires_at);
        $this->assertSame(0, (int) $verification->attempts);

        Mail::assertSent(EmailVerificationMail::class);
    }

    public function test_confirm_verification_code_successfully_marks_email_verified(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/account/email/verification/send')->assertOk();

        $code = null;
        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$code): bool {
            $code = $mail->code;
            return true;
        });
        $this->assertIsString($code);

        $this->postJson('/api/account/email/verification/confirm', [
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.')
            ->assertJsonPath('data.verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_confirm_verification_code_rejects_invalid_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/account/email/verification/send')->assertOk();

        $this->postJson('/api/account/email/verification/confirm', [
            'code' => '11111-11111',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'EMAIL_VERIFICATION_CODE_INVALID');
    }

    public function test_confirm_verification_code_rejects_expired_code(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        EmailVerification::query()->create([
            'user_id' => $user->id,
            'email' => strtolower((string) $user->email),
            'purpose' => EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
            'code_hash' => Hash::make('1234512345'),
            'expires_at' => now()->subMinute(),
            'attempts' => 0,
            'last_sent_at' => now()->subMinutes(2),
        ]);

        $this->postJson('/api/account/email/verification/confirm', [
            'code' => '12345-12345',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'EMAIL_VERIFICATION_CODE_EXPIRED');
    }

    public function test_confirm_verification_code_rejects_when_attempts_exceeded(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        EmailVerification::query()->create([
            'user_id' => $user->id,
            'email' => strtolower((string) $user->email),
            'purpose' => EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
            'code_hash' => Hash::make('1234512345'),
            'expires_at' => now()->addMinutes(20),
            'attempts' => 8,
            'last_sent_at' => now()->subMinutes(2),
        ]);

        $this->postJson('/api/account/email/verification/confirm', [
            'code' => '12345-12345',
        ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED');
    }

    public function test_send_verification_code_is_rate_limited(): void
    {
        Mail::fake();
        config()->set('email_verification.max_send_per_hour', 1);

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/account/email/verification/send')->assertOk();

        $this->travel(11)->seconds();

        $this->postJson('/api/account/email/verification/send')
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'EMAIL_VERIFICATION_SEND_RATE_LIMIT');
    }

    public function test_email_change_for_verified_email_requires_current_email_confirmation(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'requires_email_verification' => true,
            'email_verified_at' => now(),
            'email' => 'current@example.com',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/account/email/change/request', [
            'new_email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.pending_email_change.new_email', 'new@example.com');

        $pending = EmailChangeRequest::query()->latest('id')->firstOrFail();

        $this->postJson('/api/account/email/change/confirm-new')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'EMAIL_CHANGE_CURRENT_CONFIRMATION_REQUIRED');

        $this->postJson('/api/account/email/change/confirm-current')->assertOk();

        $currentCode = null;
        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$currentCode): bool {
            if ($mail->purpose !== EmailVerification::PURPOSE_EMAIL_CHANGE_CURRENT) {
                return false;
            }

            $currentCode = $mail->code;
            return true;
        });
        $this->assertIsString($currentCode);

        $confirmCurrentResponse = $this->postJson('/api/account/email/change/confirm-current', [
            'code' => $currentCode,
        ])->assertOk();

        $this->assertNotNull(data_get($confirmCurrentResponse->json(), 'data.pending_email_change.current_email_confirmed_at'));

        $this->postJson('/api/account/email/change/confirm-new')
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.verified', false);

        $user->refresh();
        $pending->refresh();

        $this->assertSame('new@example.com', strtolower((string) $user->email));
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($pending->new_email_applied_at);

        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail): bool {
            return $mail->purpose === EmailVerification::PURPOSE_ACCOUNT_VERIFICATION;
        });
    }

    public function test_verified_middleware_returns_email_not_verified_code(): void
    {
        $user = User::factory()->create([
            'requires_email_verification' => true,
            'email_verified_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', [
            'content' => 'Blocked post for unverified user.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('action', 'GO_TO_SETTINGS_EMAIL');
    }

    public function test_user_without_requires_email_verification_flag_is_not_blocked(): void
    {
        $user = User::factory()->create([
            'requires_email_verification' => false,
            'email_verified_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', [
            'content' => 'Legacy unverified user can post.',
        ])->assertCreated();
    }
}

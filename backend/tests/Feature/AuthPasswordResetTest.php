<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_hashed_reset_code_for_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'reset-me@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ak ucet existuje, poslali sme obnovovaci kod na vas e-mail.');

        $verification = EmailVerification::query()->firstOrFail();
        $this->assertSame($user->id, $verification->user_id);
        $this->assertSame(EmailVerification::PURPOSE_PASSWORD_RESET, $verification->purpose);
        $this->assertSame('reset-me@example.com', $verification->email);
        $this->assertNotNull($verification->expires_at);
        $this->assertTrue(str_starts_with((string) $verification->code_hash, '$'));

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($verification): bool {
            $normalizedCode = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $mail->code));
            if (! is_string($normalizedCode) || $normalizedCode === '') {
                return false;
            }

            return Hash::check($normalizedCode, (string) $verification->code_hash);
        });
    }

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'missing-user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ak ucet existuje, poslali sme obnovovaci kod na vas e-mail.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_verifications', 0);
    }

    public function test_forgot_password_returns_service_unavailable_when_mail_delivery_fails(): void
    {
        User::factory()->create([
            'email' => 'delivery-fail@example.com',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('delivery-fail@example.com')
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(PasswordResetCodeMail::class))
            ->andThrow(new \RuntimeException('SMTP connection failed'));

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'delivery-fail@example.com',
        ])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PASSWORD_RESET_DELIVERY_FAILED')
            ->assertJsonPath('message', 'Obnovovaci kod sa nepodarilo odoslat. Skuste to znova neskor.');

        $this->assertDatabaseCount('email_verifications', 0);
    }

    public function test_password_reset_updates_password_when_code_is_valid(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset-ok@example.com',
            'password' => Hash::make('old-password-123'),
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'reset-ok@example.com',
        ])->assertOk();

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;
            return true;
        });
        $this->assertIsString($code);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'reset-ok@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Heslo bolo úspešne obnovené. Teraz sa môžete prihlásiť.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', (string) $user->password));

        $verification = EmailVerification::query()->firstOrFail();
        $this->assertNotNull($verification->consumed_at);
    }

    public function test_password_reset_returns_invalid_code_error_for_bad_format_or_value(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'invalid-code@example.com',
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'invalid-code@example.com',
        ])->assertOk();

        $this->postJson('/api/auth/password/reset', [
            'email' => 'invalid-code@example.com',
            'code' => 'abc',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PASSWORD_RESET_CODE_INVALID')
            ->assertJsonPath('message', 'Zadali ste neplatny kod. Mal by mat tvar XXXXX-XXXXX.');

        $this->postJson('/api/auth/password/reset', [
            'email' => 'invalid-code@example.com',
            'code' => '11111-11111',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PASSWORD_RESET_CODE_INVALID')
            ->assertJsonPath('message', 'Zadali ste neplatny kod. Mal by mat tvar XXXXX-XXXXX.');
    }

    public function test_password_reset_mail_falls_back_to_global_from_address_when_verification_sender_is_missing(): void
    {
        config([
            'mail.from.address' => 'noreply@astrokomunita.sk',
            'mail.from.name' => 'Astrokomunita',
            'mail.verification_from.address' => null,
            'mail.verification_from.name' => 'Astrokomunita',
        ]);

        $mail = new PasswordResetCodeMail('12345-67890');
        $envelope = $mail->envelope();

        $this->assertSame('noreply@astrokomunita.sk', $envelope->from->address);
        $this->assertSame('Astrokomunita', $envelope->from->name);
    }
}

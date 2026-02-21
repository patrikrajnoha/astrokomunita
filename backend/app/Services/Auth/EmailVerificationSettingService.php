<?php

namespace App\Services\Auth;

use App\Models\AppSetting;

class EmailVerificationSettingService
{
    public const REQUIRE_EMAIL_VERIFICATION_KEY = 'auth.require_email_verification';

    /**
     * @return array{require_email_verification:bool}
     */
    public function payload(): array
    {
        return [
            'require_email_verification' => $this->requiresEmailVerification(),
        ];
    }

    public function requiresEmailVerification(): bool
    {
        return AppSetting::getBool(self::REQUIRE_EMAIL_VERIFICATION_KEY, true);
    }

    /**
     * @return array{require_email_verification:bool}
     */
    public function updateRequiresEmailVerification(bool $required): array
    {
        AppSetting::put(
            self::REQUIRE_EMAIL_VERIFICATION_KEY,
            $required ? '1' : '0'
        );

        return $this->payload();
    }
}

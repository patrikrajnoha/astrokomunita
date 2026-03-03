<?php

namespace App\Services\Auth;

use App\Models\AppSetting;

class EmailVerificationSettingService
{
    public const REQUIRE_EMAIL_VERIFICATION_FOR_NEW_USERS_KEY = 'auth.require_email_verification_for_new_users';
    public const LEGACY_REQUIRE_EMAIL_VERIFICATION_KEY = 'auth.require_email_verification';

    /**
     * @return array{require_email_verification_for_new_users:bool,require_email_verification:bool}
     */
    public function payload(): array
    {
        $required = $this->requiresEmailVerificationForNewUsers();

        return [
            'require_email_verification_for_new_users' => $required,
            // Backward compatible key used by older clients.
            'require_email_verification' => $required,
        ];
    }

    public function requiresEmailVerificationForNewUsers(): bool
    {
        $default = ! app()->environment('local');

        $newValue = AppSetting::getString(self::REQUIRE_EMAIL_VERIFICATION_FOR_NEW_USERS_KEY);
        if ($newValue !== null) {
            return AppSetting::getBool(self::REQUIRE_EMAIL_VERIFICATION_FOR_NEW_USERS_KEY, $default);
        }

        $legacyValue = AppSetting::getString(self::LEGACY_REQUIRE_EMAIL_VERIFICATION_KEY);
        if ($legacyValue !== null) {
            return AppSetting::getBool(self::LEGACY_REQUIRE_EMAIL_VERIFICATION_KEY, $default);
        }

        return $default;
    }

    public function requiresEmailVerification(): bool
    {
        return $this->requiresEmailVerificationForNewUsers();
    }

    /**
     * @return array{require_email_verification_for_new_users:bool,require_email_verification:bool}
     */
    public function updateRequiresEmailVerificationForNewUsers(bool $required): array
    {
        AppSetting::put(
            self::REQUIRE_EMAIL_VERIFICATION_FOR_NEW_USERS_KEY,
            $required ? '1' : '0'
        );

        // Keep legacy key in sync while older code paths still exist.
        AppSetting::put(
            self::LEGACY_REQUIRE_EMAIL_VERIFICATION_KEY,
            $required ? '1' : '0'
        );

        return $this->payload();
    }

    /**
     * @return array{require_email_verification_for_new_users:bool,require_email_verification:bool}
     */
    public function updateRequiresEmailVerification(bool $required): array
    {
        return $this->updateRequiresEmailVerificationForNewUsers($required);
    }
}

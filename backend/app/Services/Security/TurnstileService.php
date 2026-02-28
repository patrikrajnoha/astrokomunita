<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileService
{
    private const MISSING_SECRET_LOGGED_ATTRIBUTE = 'turnstile_missing_secret_logged';

    public function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    public function hasSecretKey(): bool
    {
        return trim((string) config('services.turnstile.secret_key')) !== '';
    }

    public function logMissingSecretWarningOnce(): void
    {
        $request = request();

        if ($request instanceof Request && $request->attributes->get(self::MISSING_SECRET_LOGGED_ATTRIBUTE)) {
            return;
        }

        if ($request instanceof Request) {
            $request->attributes->set(self::MISSING_SECRET_LOGGED_ATTRIBUTE, true);
        }

        Log::warning('Turnstile is enabled but TURNSTILE_SECRET_KEY is missing.', [
            'path' => $request instanceof Request ? $request->path() : null,
        ]);
    }

    public function verify(string $token, ?string $ip): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (! $this->hasSecretKey()) {
            return false;
        }

        $secretKey = trim((string) config('services.turnstile.secret_key'));

        try {
            $response = Http::asForm()
                ->timeout(3)
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success', false);
    }
}

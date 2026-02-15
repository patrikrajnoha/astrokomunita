<?php

namespace App\Support\Http;

class SslVerificationPolicy
{
    public function shouldVerifySsl(?bool $allowInsecure = null): bool
    {
        $allowInsecure ??= (bool) config('http_client.allow_insecure_ssl', false);

        if (! app()->environment(['local', 'testing'])) {
            return true;
        }

        return ! $allowInsecure;
    }

    public function resolveVerifyOption(?string $caBundlePath = null, ?bool $allowInsecure = null): bool|string
    {
        $path = trim((string) $caBundlePath);

        if ($path !== '') {
            return $path;
        }

        return $this->shouldVerifySsl($allowInsecure);
    }
}

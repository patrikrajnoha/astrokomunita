<?php

namespace App\Services\Translation\Exceptions;

use Throwable;

class TranslationProviderUnavailableException extends TranslationClientException
{
    public function __construct(
        string $providerName,
        string $message = 'Translation provider is unavailable.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($providerName, $message, $code, $previous);
    }
}

<?php

namespace App\Services\Translation\Exceptions;

use Throwable;

class TranslationTimeoutException extends TranslationClientException
{
    public function __construct(
        string $providerName,
        string $message = 'Translation request timed out.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($providerName, $message, $code, $previous);
    }
}

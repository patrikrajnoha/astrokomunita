<?php

namespace App\Services\Translation\Exceptions;

use RuntimeException;
use Throwable;

class TranslationClientException extends RuntimeException
{
    public function __construct(
        private readonly string $providerName,
        string $message,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function providerName(): string
    {
        return $this->providerName;
    }
}


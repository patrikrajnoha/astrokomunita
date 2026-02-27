<?php

namespace App\Services\Translation\Grammar;

use RuntimeException;

class GrammarCheckException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'grammar_check_error',
        private readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }
}


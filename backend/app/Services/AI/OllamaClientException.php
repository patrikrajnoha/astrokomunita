<?php

namespace App\Services\AI;

use RuntimeException;

class OllamaClientException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'ollama_error',
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


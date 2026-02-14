<?php

namespace App\Services\Moderation;

use RuntimeException;

class ModerationClientException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'moderation_client_error',
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

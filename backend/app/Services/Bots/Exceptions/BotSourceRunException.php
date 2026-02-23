<?php

namespace App\Services\Bots\Exceptions;

use RuntimeException;

class BotSourceRunException extends RuntimeException
{
    /**
     * @param array<string,mixed> $contextMeta
     */
    public function __construct(
        string $message,
        private readonly string $failureReason,
        private readonly array $contextMeta = [],
        private readonly bool $markAsSkipped = true,
    ) {
        parent::__construct($message);
    }

    public function failureReason(): string
    {
        return $this->failureReason;
    }

    /**
     * @return array<string,mixed>
     */
    public function contextMeta(): array
    {
        return $this->contextMeta;
    }

    public function shouldMarkAsSkipped(): bool
    {
        return $this->markAsSkipped;
    }
}

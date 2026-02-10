<?php

namespace App\Services\Observing\Support;

use RuntimeException;

class ObservingProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $url,
        public readonly ?int $status = null,
        public readonly ?string $bodySnippet = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function toLogContext(): array
    {
        return [
            'provider' => $this->provider,
            'url' => $this->url,
            'status' => $this->status,
            'body_snippet' => $this->bodySnippet,
            'exception_class' => static::class,
            'exception_message' => $this->getMessage(),
        ];
    }
}


<?php

namespace App\Services\Translation;

class TranslationResult
{
    /**
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly string $translatedText,
        public readonly string $provider,
        public readonly array $meta = [],
        public readonly int $durationMs = 0,
        public readonly bool $fromCache = false,
    ) {
    }
}

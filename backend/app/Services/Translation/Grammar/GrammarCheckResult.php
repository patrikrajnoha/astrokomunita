<?php

namespace App\Services\Translation\Grammar;

class GrammarCheckResult
{
    /**
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly string $correctedText,
        public readonly string $provider,
        public readonly int $appliedFixes = 0,
        public readonly int $durationMs = 0,
        public readonly array $meta = [],
    ) {
    }
}


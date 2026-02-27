<?php

namespace App\Contracts;

interface TranslationClientInterface
{
    public function provider(): string;

    /**
     * @return array{
     *   text:string,
     *   provider:string,
     *   model:?string,
     *   duration_ms:int,
     *   chars:int
     * }
     */
    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): array;
}


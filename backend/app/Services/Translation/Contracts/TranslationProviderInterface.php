<?php

namespace App\Services\Translation\Contracts;

use App\Services\Translation\TranslationResult;

interface TranslationProviderInterface
{
    public function translate(string $text, string $from, string $to): TranslationResult;
}

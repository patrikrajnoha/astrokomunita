<?php

namespace App\Services\Translation\Grammar\Contracts;

use App\Services\Translation\Grammar\GrammarCheckResult;

interface GrammarCheckerInterface
{
    public function correct(string $text, string $language): GrammarCheckResult;
}


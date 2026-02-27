<?php

namespace App\Enums;

enum BotTranslationStatus: string
{
    case PENDING = 'pending';
    case DONE = 'done';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}


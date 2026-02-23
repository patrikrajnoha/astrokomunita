<?php

namespace App\Enums;

enum BotPublishStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
}


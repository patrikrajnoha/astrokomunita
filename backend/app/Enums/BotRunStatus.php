<?php

namespace App\Enums;

enum BotRunStatus: string
{
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}

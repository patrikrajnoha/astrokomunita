<?php

namespace App\Enums;

enum BotRunStatus: string
{
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
}


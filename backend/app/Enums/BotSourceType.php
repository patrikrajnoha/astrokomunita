<?php

namespace App\Enums;

enum BotSourceType: string
{
    case RSS = 'rss';
    case API = 'api';
    case WIKIPEDIA = 'wikipedia';
}


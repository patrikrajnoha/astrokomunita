<?php

namespace App\Enums;

enum BotSourceType: string
{
    case RSS = 'rss';
    case API = 'api';
    case SCRAPE = 'scrape';
    case WIKIPEDIA = 'wikipedia';
}

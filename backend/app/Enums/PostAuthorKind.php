<?php

namespace App\Enums;

enum PostAuthorKind: string
{
    case USER = 'user';
    case BOT = 'bot';
}


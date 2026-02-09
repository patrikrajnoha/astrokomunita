<?php

namespace App\Enums;

enum RssItemStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Discarded = 'discarded';
    case Error = 'error';
}

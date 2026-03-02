<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class EventFollowTable
{
    public const PRIMARY = 'user_event_follows';

    public const LEGACY = 'favorites';

    public static function resolve(): string
    {
        if (Schema::hasTable(self::PRIMARY)) {
            return self::PRIMARY;
        }

        if (Schema::hasTable(self::LEGACY)) {
            return self::LEGACY;
        }

        return self::PRIMARY;
    }
}

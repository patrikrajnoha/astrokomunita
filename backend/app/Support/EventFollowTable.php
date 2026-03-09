<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class EventFollowTable
{
    public const PRIMARY = 'user_event_follows';

    public const LEGACY = 'favorites';

    /**
     * @var array<string,bool>
     */
    private static array $personalPlanColumnCache = [];

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

    public static function supportsPersonalPlanColumns(?string $table = null): bool
    {
        $resolved = $table ?? self::resolve();

        if (array_key_exists($resolved, self::$personalPlanColumnCache)) {
            return self::$personalPlanColumnCache[$resolved];
        }

        if (! Schema::hasTable($resolved)) {
            self::$personalPlanColumnCache[$resolved] = false;

            return false;
        }

        foreach (self::personalPlanColumns() as $column) {
            if (! Schema::hasColumn($resolved, $column)) {
                self::$personalPlanColumnCache[$resolved] = false;

                return false;
            }
        }

        self::$personalPlanColumnCache[$resolved] = true;

        return true;
    }

    /**
     * @return list<string>
     */
    public static function personalPlanColumns(): array
    {
        return [
            'personal_note',
            'reminder_at',
            'planned_time',
            'planned_location_label',
        ];
    }
}

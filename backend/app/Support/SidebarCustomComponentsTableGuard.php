<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Throwable;

class SidebarCustomComponentsTableGuard
{
    private const TABLE_NAME = 'sidebar_custom_components';

    public static function isMissingTable(Throwable $exception): bool
    {
        if (!$exception instanceof QueryException) {
            return false;
        }

        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $sqlState === '42S02'
            && str_contains($message, self::TABLE_NAME);
    }

    public static function missingTableResponse(): JsonResponse
    {
        $status = app()->environment(['local', 'development']) ? 500 : 503;

        return response()->json([
            'message' => 'Missing database table "sidebar_custom_components". Run migrations: php artisan migrate',
            'error_code' => 'missing_sidebar_custom_components_table',
        ], $status);
    }
}

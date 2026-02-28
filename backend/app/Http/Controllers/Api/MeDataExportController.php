<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MeDataExportController extends Controller
{
    public function __invoke(Request $request, UserDataExportService $exportService): JsonResponse
    {
        $user = $request->user();
        $payload = $exportService->export($user);
        $filename = $this->filenameForUser($user->username);

        Log::info('User data export generated', [
            'user_id' => (int) $user->id,
            'export_version' => (string) ($payload['export_version'] ?? ''),
            'ip_hash' => $this->anonymizedIp($request->ip()),
            'posts_count' => (int) ($payload['data_summary']['posts_count'] ?? 0),
            'invites_count' => (int) ($payload['data_summary']['invites_count'] ?? 0),
        ]);

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function filenameForUser(?string $username): string
    {
        $safeUsername = Str::slug((string) $username);
        if ($safeUsername === '') {
            $safeUsername = 'user';
        }

        return 'nebesky-sprievodca-export-'.$safeUsername.'-'.now()->utc()->format('Ymd_His').'.json';
    }

    private function anonymizedIp(?string $ip): ?string
    {
        $normalized = trim((string) $ip);

        return $normalized !== ''
            ? hash('sha256', $normalized.'|'.(string) config('app.key'))
            : null;
    }
}

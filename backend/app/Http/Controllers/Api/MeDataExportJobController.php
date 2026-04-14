<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateUserDataExportJob;
use App\Models\UserDataExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MeDataExportJobController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (!Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Aktuálne heslo nie je správne.',
                'errors' => [
                    'current_password' => ['Aktuálne heslo nie je správne.'],
                ],
            ], 422);
        }

        $activeJob = UserDataExportJob::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [UserDataExportJob::STATUS_PENDING, UserDataExportJob::STATUS_PROCESSING])
            ->latest('id')
            ->first();

        if ($activeJob) {
            return response()->json($this->payload($activeJob));
        }

        $exportJob = UserDataExportJob::query()->create([
            'user_id' => $user->id,
            'status' => UserDataExportJob::STATUS_PENDING,
            'request_ip_hash' => $this->anonymizedIp($request->ip()),
            'file_name' => $this->filenameForUser($user->username),
        ]);

        GenerateUserDataExportJob::dispatch((int) $exportJob->id)->afterCommit();

        return response()->json($this->payload($exportJob->fresh()), 202);
    }

    public function show(Request $request, UserDataExportJob $job): JsonResponse
    {
        if ((int) $job->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        return response()->json($this->payload($job->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?UserDataExportJob $job): array
    {
        if (!$job) {
            return [
                'status' => UserDataExportJob::STATUS_FAILED,
                'message' => 'Export job sa nenašiel.',
            ];
        }

        $downloadUrl = null;
        if (
            $job->status === UserDataExportJob::STATUS_READY
            && !empty($job->file_path)
            && ($job->expires_at === null || $job->expires_at->isFuture())
        ) {
            $downloadUrl = URL::temporarySignedRoute(
                'me.export.jobs.download',
                now()->addMinutes(15),
                ['job' => $job->id],
                false
            );
        }

        return [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'file_name' => (string) ($job->file_name ?: ''),
            'size_bytes' => $job->size_bytes !== null ? (int) $job->size_bytes : null,
            'checksum_sha256' => $job->checksum_sha256,
            'created_at' => optional($job->created_at)?->toIso8601String(),
            'started_at' => optional($job->started_at)?->toIso8601String(),
            'completed_at' => optional($job->completed_at)?->toIso8601String(),
            'expires_at' => optional($job->expires_at)?->toIso8601String(),
            'download_url' => $downloadUrl,
            'error_message' => $job->status === UserDataExportJob::STATUS_FAILED ? $job->error_message : null,
        ];
    }

    private function anonymizedIp(?string $ip): ?string
    {
        $normalized = trim((string) $ip);

        return $normalized !== ''
            ? hash('sha256', $normalized.'|'.(string) config('app.key'))
            : null;
    }

    private function filenameForUser(?string $username): string
    {
        $safeUsername = Str::slug((string) $username);
        if ($safeUsername === '') {
            $safeUsername = 'user';
        }

        return 'nebesky-sprievodca-export-'.$safeUsername.'-'.now()->utc()->format('Ymd_His').'.zip';
    }
}

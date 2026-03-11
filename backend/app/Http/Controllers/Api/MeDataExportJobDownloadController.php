<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDataExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeDataExportJobDownloadController extends Controller
{
    public function __invoke(Request $request, UserDataExportJob $job)
    {
        if (! $request->hasValidSignature(false)) {
            return response()->json([
                'message' => 'Download link je neplatny alebo expirovany.',
            ], 403);
        }

        $user = $request->user();
        if (! $user || (int) $job->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Zakazane.',
            ], 403);
        }

        if ((string) $job->status !== UserDataExportJob::STATUS_READY || empty($job->file_path)) {
            return response()->json([
                'message' => 'Export este nie je pripraveny.',
            ], 409);
        }

        if ($job->expires_at !== null && $job->expires_at->isPast()) {
            return response()->json([
                'message' => 'Export vyprsal. Vytvorte novy export.',
            ], 410);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists((string) $job->file_path)) {
            return response()->json([
                'message' => 'Export subor sa nenasiel.',
            ], 404);
        }

        $downloadName = $job->file_name ?: ('nebesky-sprievodca-export-' . (int) $job->id . '.zip');
        $contentType = Str::endsWith(strtolower($downloadName), '.zip')
            ? 'application/zip'
            : 'application/json';

        return $disk->download((string) $job->file_path, $downloadName, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\UserDataExportJob;
use App\Services\UserDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateUserDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly int $exportJobId,
    ) {
    }

    public function handle(UserDataExportService $exportService): void
    {
        $exportJob = UserDataExportJob::query()
            ->with('user:id,username')
            ->find($this->exportJobId);

        if (! $exportJob || ! $exportJob->user) {
            return;
        }

        $exportJob->forceFill([
            'status' => UserDataExportJob::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $payload = $exportService->export($exportJob->user);
            $attachmentSources = $exportService->collectAttachmentSources($exportJob->user);

            $fileName = $exportJob->file_name ?: $this->filenameForUser($exportJob->user->username);
            $filePath = sprintf('user-exports/%d/%s', (int) $exportJob->id, $fileName);
            $disk = Storage::disk('local');
            $absolutePath = $disk->path($filePath);
            File::ensureDirectoryExists(dirname($absolutePath));

            $zip = new \ZipArchive();
            $openResult = $zip->open($absolutePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new \RuntimeException('Export zip cannot be created (code: ' . (string) $openResult . ').');
            }

            $temporaryFiles = [];
            $includedFiles = [];
            foreach ($attachmentSources as $source) {
                $tempPath = $this->copySourceToTemporaryFile((string) $source['disk'], (string) $source['path']);
                if ($tempPath === null) {
                    continue;
                }

                $temporaryFiles[] = $tempPath;
                if ($zip->addFile($tempPath, (string) $source['zip_path']) !== true) {
                    continue;
                }

                $includedFiles[] = [
                    'post_id' => (int) $source['post_id'],
                    'source' => (string) $source['source'],
                    'zip_path' => (string) $source['zip_path'],
                    'mime' => $source['mime'] ?? null,
                    'size_bytes' => isset($source['size_bytes']) && $source['size_bytes'] !== null
                        ? (int) $source['size_bytes']
                        : null,
                ];
            }

            $payload['attachments'] = [
                'included_count' => count($includedFiles),
                'requested_count' => count($attachmentSources),
                'missing_count' => max(0, count($attachmentSources) - count($includedFiles)),
                'files' => $includedFiles,
            ];
            $payload = $exportService->withChecksum($payload);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! is_string($json)) {
                throw new \RuntimeException('Export serialization failed.');
            }

            if ($zip->addFromString('export.json', $json) !== true) {
                throw new \RuntimeException('Failed to write export.json into zip.');
            }

            if ($zip->close() !== true) {
                throw new \RuntimeException('Failed to finalize export zip.');
            }

            foreach ($temporaryFiles as $tempFile) {
                if (is_string($tempFile) && $tempFile !== '' && file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }

            $exportJob->forceFill([
                'status' => UserDataExportJob::STATUS_READY,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'size_bytes' => (int) filesize($absolutePath),
                'checksum_sha256' => (string) ($payload['checksum_sha256'] ?? hash('sha256', $json)),
                'completed_at' => now(),
                'expires_at' => now()->addDay(),
            ])->save();
        } catch (\Throwable $exception) {
            if (isset($zip) && $zip instanceof \ZipArchive) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }
            if (isset($temporaryFiles) && is_array($temporaryFiles)) {
                foreach ($temporaryFiles as $tempFile) {
                    if (is_string($tempFile) && $tempFile !== '' && file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
            }

            $exportJob->forceFill([
                'status' => UserDataExportJob::STATUS_FAILED,
                'error_message' => Str::limit($exception->getMessage(), 500, '...'),
                'completed_at' => now(),
            ])->save();

            Log::warning('User data export generation failed.', [
                'export_job_id' => (int) $exportJob->id,
                'user_id' => (int) $exportJob->user_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function filenameForUser(?string $username): string
    {
        $safeUsername = Str::slug((string) $username);
        if ($safeUsername === '') {
            $safeUsername = 'user';
        }

        return 'nebesky-sprievodca-export-'.$safeUsername.'-'.now()->utc()->format('Ymd_His').'.zip';
    }

    private function copySourceToTemporaryFile(string $diskName, string $path): ?string
    {
        $disk = Storage::disk($diskName);

        try {
            if (!$disk->exists($path)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $source = null;
        $target = null;
        $temporaryPath = null;

        try {
            $source = $disk->readStream($path);
            if (!is_resource($source)) {
                return null;
            }

            $baseTemp = tempnam(sys_get_temp_dir(), 'export-attachment-');
            if (!is_string($baseTemp) || $baseTemp === '') {
                return null;
            }

            $temporaryPath = $baseTemp;
            $target = fopen($temporaryPath, 'wb');
            if (!is_resource($target)) {
                return null;
            }

            stream_copy_to_stream($source, $target);

            return $temporaryPath;
        } catch (\Throwable) {
            if (is_string($temporaryPath) && $temporaryPath !== '' && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }

            return null;
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
        }
    }
}

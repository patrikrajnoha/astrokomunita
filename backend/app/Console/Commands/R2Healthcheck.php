<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class R2Healthcheck extends Command
{
    protected $signature = 'storage:r2-healthcheck';

    protected $description = 'Write and verify a small file on the configured public and private media disks.';

    public function handle(): int
    {
        $timestamp = now()->format('Ymd_His');
        $token = Str::lower(Str::random(12));

        $checks = [
            'public' => (string) config('media.disk', 'public'),
            'private' => (string) config('media.private_disk', 'local'),
        ];

        $failed = false;

        foreach ($checks as $label => $diskName) {
            $path = sprintf('healthchecks/%s/%s_%s.txt', $label, $timestamp, $token);
            $contents = sprintf(
                "astrokomunita storage healthcheck\nlabel=%s\ndisk=%s\ntimestamp=%s\n",
                $label,
                $diskName,
                now()->toIso8601String()
            );

            try {
                $written = Storage::disk($diskName)->put($path, $contents);
                $exists = Storage::disk($diskName)->exists($path);
            } catch (Throwable $e) {
                $this->error(sprintf('[%s] disk=%s error=%s', $label, $diskName, $e->getMessage()));
                $failed = true;

                continue;
            }

            if (!$written || !$exists) {
                $this->error(sprintf(
                    '[%s] disk=%s write=%s exists=%s path=%s',
                    $label,
                    $diskName,
                    $written ? 'ok' : 'failed',
                    $exists ? 'yes' : 'no',
                    $path
                ));
                $failed = true;

                continue;
            }

            $this->info(sprintf('[%s] disk=%s path=%s exists=yes', $label, $diskName, $path));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

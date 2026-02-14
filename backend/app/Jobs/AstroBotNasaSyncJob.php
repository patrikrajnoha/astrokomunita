<?php

namespace App\Jobs;

use App\Exceptions\AstroBotSyncInProgressException;
use App\Services\AstroBotNasaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AstroBotNasaSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function handle(AstroBotNasaService $service): void
    {
        try {
            $service->syncWithLock('scheduler');
        } catch (AstroBotSyncInProgressException) {
            Log::info('AstroBot NASA sync skipped because previous run is still active.');
        }
    }
}

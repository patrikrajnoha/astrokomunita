<?php

namespace App\Jobs;

use App\Models\RssItem;
use App\Services\AstroBotItemProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateAndPublishAstroBotItemJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $itemId,
    ) {
    }

    public function uniqueId(): string
    {
        $item = RssItem::query()->find($this->itemId);
        return $item?->stable_key ?? ('rss-item-' . $this->itemId);
    }

    public function handle(AstroBotItemProcessorService $processor): void
    {
        $item = RssItem::query()->find($this->itemId);
        if (! $item) {
            return;
        }

        try {
            $processor->process($item);
        } catch (\Throwable $e) {
            Log::warning('AstroBot item processing failed', [
                'item_id' => $this->itemId,
                'message' => $e->getMessage(),
            ]);

            $item->update([
                'status' => RssItem::STATUS_NEEDS_REVIEW,
                'last_error' => $e->getMessage(),
            ]);
        }
    }
}


<?php

namespace App\Jobs;

use App\Models\RssItem;
use App\Services\Translation\TranslationServiceException;
use App\Services\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateRssItemJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $rssItemId,
        public readonly bool $force = false,
    ) {
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return 'rss-item-translation-' . $this->rssItemId . '-' . ($this->force ? 'force' : 'normal');
    }

    public function handle(TranslationService $translationService): void
    {
        $item = RssItem::query()->find($this->rssItemId);
        if (! $item) {
            return;
        }

        if (
            ! $this->force
            && $item->translation_status === RssItem::TRANSLATION_DONE
            && filled($item->translated_title)
            && ($item->summary === null || filled($item->translated_summary))
        ) {
            return;
        }

        $originalTitle = (string) ($item->original_title ?: $item->title);
        $originalSummary = $item->original_summary ?? $item->summary;

        $item->update([
            'original_title' => $originalTitle,
            'original_summary' => $originalSummary,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'translation_error' => null,
        ]);

        try {
            $translatedTitle = $translationService->translateEnToSk($originalTitle, 'astronomy');
            $translatedSummary = $originalSummary !== null
                ? $translationService->translateEnToSk((string) $originalSummary, 'astronomy')
                : null;

            $item->update([
                'translated_title' => $translatedTitle,
                'translated_summary' => $translatedSummary,
                'translation_status' => RssItem::TRANSLATION_DONE,
                'translation_error' => null,
                'translated_at' => now(),
            ]);

            Log::info('RSS item translated', [
                'rss_item_id' => $item->id,
                'force' => $this->force,
            ]);
        } catch (TranslationServiceException $exception) {
            $item->update([
                'translation_status' => RssItem::TRANSLATION_FAILED,
                'translation_error' => $exception->errorCode(),
            ]);

            Log::warning('RSS item translation failed', [
                'rss_item_id' => $item->id,
                'error_code' => $exception->errorCode(),
                'status_code' => $exception->statusCode(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

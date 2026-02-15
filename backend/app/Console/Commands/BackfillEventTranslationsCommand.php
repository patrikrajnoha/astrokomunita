<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\Translation\TranslationServiceException;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillEventTranslationsCommand extends Command
{
    protected $signature = 'events:backfill-translations
                            {--limit=0 : Max approved candidates to process (0 = all)}
                            {--dry-run : Do not persist changes}
                            {--force : Retranslate even if translated fields already exist}';

    protected $description = 'Backfill Slovak translations for approved event candidates and sync to published events.';

    public function __construct(
        private readonly TranslationService $translationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = EventCandidate::query()
            ->where('status', EventCandidate::STATUS_APPROVED)
            ->whereNotNull('published_event_id')
            ->orderBy('id');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('translated_title')
                    ->orWhere('translated_title', '')
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('description')
                            ->where(function ($s) {
                                $s->whereNull('translated_description')
                                    ->orWhere('translated_description', '');
                            });
                    });
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $candidates = $query->get();
        $total = $candidates->count();

        if ($total === 0) {
            $this->info('No candidates require backfill.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} approved candidates...");

        $processed = 0;
        $translated = 0;
        $failed = 0;
        $eventsUpdated = 0;

        foreach ($candidates as $candidate) {
            $processed++;

            $originalTitle = (string) ($candidate->original_title ?: $candidate->title);
            $originalDescription = $candidate->original_description ?? $candidate->description;

            $translatedTitle = (string) ($candidate->translated_title ?? '');
            $translatedDescription = $candidate->translated_description;
            $errorCode = null;

            try {
                if ($force || $translatedTitle === '') {
                    $translatedTitle = $this->translationService->translateEnToSk($originalTitle, 'astronomy');
                }

                if ($originalDescription !== null && ($force || blank($translatedDescription))) {
                    $translatedDescription = $this->translationService->translateEnToSk((string) $originalDescription, 'astronomy');
                }
            } catch (TranslationServiceException $exception) {
                $failed++;
                $errorCode = $exception->errorCode();
                $this->warn("Candidate {$candidate->id}: translation failed ({$errorCode})");
            }

            $status = $errorCode === null
                ? EventCandidate::TRANSLATION_DONE
                : EventCandidate::TRANSLATION_FAILED;

            if (! $dryRun) {
                $candidate->update([
                    'original_title' => $originalTitle,
                    'original_description' => $originalDescription,
                    'translated_title' => $translatedTitle !== '' ? $translatedTitle : null,
                    'translated_description' => $translatedDescription,
                    'translation_status' => $status,
                    'translation_error' => $errorCode,
                    'translated_at' => $errorCode === null ? now() : null,
                ]);

                $eventUpdates = [];
                if ($translatedTitle !== '') {
                    $eventUpdates['title'] = $translatedTitle;
                }
                if (filled($translatedDescription)) {
                    $eventUpdates['description'] = (string) $translatedDescription;
                }

                if ($eventUpdates !== []) {
                    $translatedShort = null;
                    if (filled($translatedDescription)) {
                        $translatedShort = Str::limit((string) $translatedDescription, 180);
                    } elseif ($translatedTitle !== '') {
                        $translatedShort = Str::limit($translatedTitle, 180);
                    }

                    if ($translatedShort !== null) {
                        $eventUpdates['short'] = $translatedShort;
                    }

                    $affected = Event::query()
                        ->whereKey($candidate->published_event_id)
                        ->update($eventUpdates);
                    if ($affected > 0) {
                        $eventsUpdated += $affected;
                    }
                }
            }

            if ($errorCode === null) {
                $translated++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Backfill summary processed=%d translated=%d failed=%d events_updated=%d dry_run=%s',
            $processed,
            $translated,
            $failed,
            $eventsUpdated,
            $dryRun ? 'yes' : 'no'
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

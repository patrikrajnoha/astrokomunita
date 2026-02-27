<?php

namespace App\Services\Translation;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\TranslationService;
use Illuminate\Support\Str;

class EventTranslationBackfillService
{
    public function __construct(
        private readonly TranslationService $translationService,
    ) {
    }

    /**
     * @return array{
     *   dry_run:bool,
     *   force:bool,
     *   total_candidates:int,
     *   processed:int,
     *   translated:int,
     *   failed:int,
     *   events_updated:int,
     *   failures:array<int,array{candidate_id:int,error_code:string}>
     * }
     */
    public function run(int $limit = 0, bool $dryRun = false, bool $force = false): array
    {
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

        $summary = [
            'dry_run' => $dryRun,
            'force' => $force,
            'total_candidates' => $candidates->count(),
            'processed' => 0,
            'translated' => 0,
            'failed' => 0,
            'events_updated' => 0,
            'failures' => [],
        ];

        foreach ($candidates as $candidate) {
            $summary['processed']++;

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
                $errorCode = $exception->errorCode();
                $summary['failed']++;
                if (count($summary['failures']) < 50) {
                    $summary['failures'][] = [
                        'candidate_id' => (int) $candidate->id,
                        'error_code' => $errorCode,
                    ];
                }
            }

            $status = $errorCode === null
                ? EventCandidate::TRANSLATION_DONE
                : EventCandidate::TRANSLATION_FAILED;

            $eventUpdates = [];
            if ($translatedTitle !== '') {
                $eventUpdates['title'] = $translatedTitle;
            }
            if (filled($translatedDescription)) {
                $eventUpdates['description'] = (string) $translatedDescription;
            }

            $translatedShort = null;
            if (filled($translatedDescription)) {
                $translatedShort = Str::limit((string) $translatedDescription, 180);
            } elseif ($translatedTitle !== '') {
                $translatedShort = Str::limit($translatedTitle, 180);
            }
            if ($translatedShort !== null) {
                $eventUpdates['short'] = $translatedShort;
            }

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
            }

            if ($eventUpdates !== []) {
                if ($dryRun) {
                    $summary['events_updated']++;
                } else {
                    $affected = Event::query()
                        ->whereKey($candidate->published_event_id)
                        ->update($eventUpdates);
                    $summary['events_updated'] += $affected;
                }
            }

            if ($errorCode === null) {
                $summary['translated']++;
            }
        }

        return $summary;
    }
}


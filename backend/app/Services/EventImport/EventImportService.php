<?php

namespace App\Services\EventImport;

use App\Models\EventCandidate;
use App\Services\EventImport\Parsers\EventSourceParser;
use Illuminate\Support\Str;

class EventImportService
{
    public function __construct(
        private HtmlSourceFetcher $fetcher,
    ) {
    }

    public function importFromUrl(string $sourceName, string $sourceUrl, EventSourceParser $parser): EventImportResult
    {
        $payload = $this->fetcher->fetch($sourceUrl);
        $items = $parser->parse($payload);

        $total = count($items);
        $imported = 0;
        $duplicates = 0;

        foreach ($items as $item) {
            $sourceUid = $item->sourceUid ?: $this->buildSourceUid($item);
            $sourceHash = $this->buildSourceHash($sourceName, $sourceUid, $payload);

            $exists = EventCandidate::query()
                ->where('source_name', $sourceName)
                ->where(function ($query) use ($sourceUid, $sourceHash) {
                    if ($sourceUid !== null) {
                        $query->orWhere('source_uid', $sourceUid);
                    }

                    $query->orWhere('source_hash', $sourceHash);
                })
                ->exists();

            if ($exists) {
                $duplicates++;
                continue;
            }

            EventCandidate::create([
                'source_name' => $sourceName,
                'source_url' => $sourceUrl,
                'source_uid' => $sourceUid,
                'source_hash' => $sourceHash,
                'title' => $item->title,
                'type' => $item->type,
                'start_at' => $item->startAt,
                'end_at' => $item->endAt,
                'max_at' => $item->maxAt,
                'short' => $item->short,
                'description' => $item->description,
                'raw_payload' => $payload,
                'status' => EventCandidate::STATUS_PENDING,
            ]);

            $imported++;
        }

        return new EventImportResult($total, $imported, $duplicates);
    }

    private function buildSourceUid(EventCandidateData $item): string
    {
        $parts = [
            Str::slug($item->title, '-'),
            $item->type,
            $item->startAt?->format('Y-m-d H:i:s'),
            $item->endAt?->format('Y-m-d H:i:s'),
        ];

        return trim(implode('|', array_filter($parts, static fn ($part) => $part !== null && $part !== '')));
    }

    private function buildSourceHash(string $sourceName, ?string $sourceUid, string $payload): string
    {
        return hash('sha256', implode('|', [
            $sourceName,
            $sourceUid,
            hash('sha256', $payload),
        ]));
    }
}

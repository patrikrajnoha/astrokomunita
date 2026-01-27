<?php

namespace App\Services\EventImport;

use App\Models\EventCandidate;
use App\Services\EventImport\Parsers\EventSourceParser;
use Illuminate\Support\Str;

class EventImportService
{
    public function __construct(
        private HtmlSourceFetcher $fetcher,
        private EventTypeClassifier $typeClassifier,
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
            // Bez titulu nemá zmysel ukladať
            if ($item->title === null || trim($item->title) === '') {
                continue;
            }

            // Normalizuj texty už pred generovaním UID (aby UID neobsahoval entity)
            $title = $this->normalizeText($item->title);
            if ($title === null) {
                continue;
            }

            $short = $this->normalizeText($item->short);
            $description = $this->normalizeText($item->description);

            // raw_type (čo prišlo zo zdroja) + normalized type (interná taxonómia)
            $rawType = $this->normalizeText($item->type) ?? $item->type; // fallback na pôvodný string
            $normalizedType = $this->typeClassifier->classify($rawType, $title);

            // UID stavaj z normalizovaného titulu + NORMALIZOVANÉHO typu (stabilnejšie)
            $sourceUid = $item->sourceUid ?: $this->buildSourceUidFromNormalized(
                $title,
                $normalizedType ?: 'other',
                $item->startAt,
                $item->endAt,
                $item->maxAt
            );

            // Hash viažeme aj na payload (audit/repro), aj na UID
            $sourceHash = $this->buildSourceHash($sourceName, $sourceUid, $payload);

            $exists = EventCandidate::query()
                ->where('source_name', $sourceName)
                ->where(function ($query) use ($sourceUid, $sourceHash) {
                    if ($sourceUid !== null && $sourceUid !== '') {
                        $query->orWhere('source_uid', $sourceUid);
                    }
                    $query->orWhere('source_hash', $sourceHash);
                })
                ->exists();

            if ($exists) {
                $duplicates++;
                continue;
            }

            // Bezpečný fallback (hodí sa ak by max_at bolo NOT NULL)
            $startAt = $item->startAt;
            $endAt   = $item->endAt;
            $maxAt   = $item->maxAt ?? $startAt;

            EventCandidate::create([
                'source_name' => $sourceName,
                'source_url'  => $sourceUrl,
                'source_uid'  => $sourceUid,
                'source_hash' => $sourceHash,

                'title'       => $title,

                // raw_type + normalized type
                'raw_type'    => $rawType,
                'type'        => $normalizedType,

                'start_at'    => $startAt,
                'end_at'      => $endAt,
                'max_at'      => $maxAt,

                'short'       => $short,
                'description' => $description,

                // Surový zdroj nechávame 1:1 kvôli auditovateľnosti/reprodukovateľnosti
                'raw_payload' => $payload,

                'status'      => EventCandidate::STATUS_PENDING,
            ]);

            $imported++;
        }

        return new EventImportResult($total, $imported, $duplicates);
    }

    /**
     * Stavia UID zo "čistých" hodnôt (bez HTML entít).
     */
    private function buildSourceUidFromNormalized(
        string $title,
        string $type,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $endAt,
        ?\DateTimeInterface $maxAt
    ): string {
        $parts = [
            Str::slug($title, '-'),
            $type ?: 'other',
            $startAt?->format('Y-m-d H:i:s'),
            $endAt?->format('Y-m-d H:i:s'),
            $maxAt?->format('Y-m-d H:i:s'),
        ];

        return trim(implode('|', array_filter(
            $parts,
            static fn ($part) => $part !== null && $part !== ''
        )));
    }

    private function buildSourceHash(string $sourceName, ?string $sourceUid, string $payload): string
    {
        return hash('sha256', implode('|', [
            $sourceName,
            (string) $sourceUid,
            hash('sha256', $payload),
        ]));
    }

    /**
     * Normalizuje text pre DB/UI:
     * - rieši aj dvojité enkódovanie (&amp;#176; -> &#176;)
     * - doplní chýbajúce bodkočiarky v numeric entitách (&#176N -> &#176;N)
     * - dekóduje HTML entity (&#176; -> °)
     * - zjednotí whitespace
     * - oreže okraje
     */
    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim($value);
        if ($s === '') {
            return null;
        }

        // 1) Oprava double-encoding (typicky: &amp;#176;)
        $s = str_replace(['&amp;#', '&amp;nbsp;'], ['&#', ' '], $s);

        // 2) Doplnenie bodkočiarky pre numeric entity, ak chýba:
        $s = preg_replace('/&#(\d+)(?!;)/', '&#$1;', $s) ?? $s;
        $s = preg_replace('/&#x([0-9a-fA-F]+)(?!;)/', '&#x$1;', $s) ?? $s;

        // 3) Dekódovanie HTML entít (max 2 prechody)
        for ($i = 0; $i < 2; $i++) {
            $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $s) {
                break;
            }
            $s = $decoded;
        }

        // 4) Normalizácia whitespace
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        return $s !== '' ? $s : null;
    }
}

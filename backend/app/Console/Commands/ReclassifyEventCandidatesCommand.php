<?php

namespace App\Console\Commands;

use App\Models\EventCandidate;
use App\Services\EventImport\EventTypeClassifier;
use Illuminate\Console\Command;

class ReclassifyEventCandidatesCommand extends Command
{
    protected $signature = 'events:candidates:reclassify
        {--source= : Iba pre konkrétny source_name (napr. astropixels)}
        {--status= : Iba pre konkrétny status (napr. pending)}
        {--dry : Len vypíše, nič nezapisuje}';

    protected $description = 'Recompute EventCandidate.type based on raw_type + title (and save if changed).';

    public function __construct(private readonly EventTypeClassifier $classifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->option('source');
        $status = $this->option('status');
        $dry    = (bool) $this->option('dry');

        $q = EventCandidate::query()
            ->select(['id', 'source_name', 'status', 'raw_type', 'title', 'type']);

        if ($source) $q->where('source_name', $source);
        if ($status) $q->where('status', $status);

        $total = (clone $q)->count();

        $changed = 0;
        $scanned = 0;

        $q->orderBy('id')->chunkById(500, function ($rows) use (&$changed, &$scanned, $dry) {
            foreach ($rows as $c) {
                $scanned++;

                $newType = $this->classifier->classify($c->raw_type, $c->title);

                if ($newType !== $c->type) {
                    $changed++;

                    if (!$dry) {
                        $c->type = $newType;
                        $c->save();
                    }
                }
            }
        });

        $this->info("Scanned: {$scanned} / {$total}");
        $this->info("Changed: {$changed}" . ($dry ? " (dry-run)" : ""));

        return self::SUCCESS;
    }
}

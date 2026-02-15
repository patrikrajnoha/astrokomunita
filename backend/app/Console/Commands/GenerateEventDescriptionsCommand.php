<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Events\EventDescriptionGeneratorService;
use Illuminate\Console\Command;
use Throwable;

class GenerateEventDescriptionsCommand extends Command
{
    protected $signature = 'events:generate-descriptions
                            {--limit=0 : Max events to process (0 = all)}
                            {--dry-run : Do not persist changes}
                            {--force : Regenerate even when description already exists}
                            {--ids= : Comma-separated event IDs}';

    protected $description = 'Generate Slovak event descriptions using local open-source AI model.';

    public function __construct(
        private readonly EventDescriptionGeneratorService $generatorService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $ids = $this->parseIds((string) $this->option('ids'));

        $query = Event::query()
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereNull('short')
                    ->orWhere('short', '');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $events = $query->get();
        if ($events->isEmpty()) {
            $this->info('No events require description generation.');
            return self::SUCCESS;
        }

        $summary = [
            'processed' => 0,
            'generated' => 0,
            'failed' => 0,
            'updated' => 0,
            'dry_run' => $dryRun,
        ];

        $this->info("Processing {$events->count()} events...");

        foreach ($events as $event) {
            $summary['processed']++;

            try {
                $generated = $this->generatorService->generateForEvent($event);
                $summary['generated']++;

                if (! $dryRun) {
                    $event->update([
                        'description' => $generated['description'],
                        'short' => $generated['short'],
                    ]);
                    $summary['updated']++;
                } else {
                    $summary['updated']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                $this->warn(sprintf(
                    'Event %d failed: %s',
                    (int) $event->id,
                    $exception->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Generate summary processed=%d generated=%d failed=%d updated=%d dry_run=%s',
            $summary['processed'],
            $summary['generated'],
            $summary['failed'],
            $summary['updated'],
            $summary['dry_run'] ? 'yes' : 'no'
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int,int>
     */
    private function parseIds(string $raw): array
    {
        $value = trim($raw);
        if ($value === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $item): int => (int) trim($item),
            explode(',', $value)
        );

        $parts = array_values(array_filter($parts, static fn (int $id): bool => $id > 0));
        return array_values(array_unique($parts));
    }
}


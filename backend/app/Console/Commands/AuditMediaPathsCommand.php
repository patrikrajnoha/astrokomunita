<?php

namespace App\Console\Commands;

use App\Support\MediaAudit\MediaPathAuditService;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class AuditMediaPathsCommand extends Command
{
    protected $signature = 'media:audit
        {--area=* : Limit audit to one or more areas: observations, polls, posts, profiles}
        {--format=table : Console output format: table or json}
        {--sample=20 : Number of non-valid rows to show in console output}
        {--export= : Optional export path (.json or .csv)}';

    protected $description = 'Read-only audit of media path columns and storage availability.';

    public function __construct(
        private readonly MediaPathAuditService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        if (!in_array($format, ['table', 'json'], true)) {
            $this->error('Unsupported format. Use table or json.');

            return self::FAILURE;
        }

        $sampleLimit = max(0, (int) $this->option('sample'));
        $requestedAreas = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) $this->option('area')
        )));

        $normalizedAreas = $this->audit->normalizeAreas($requestedAreas);
        if ($requestedAreas !== [] && $normalizedAreas === []) {
            $this->error('No valid areas were selected.');

            return self::FAILURE;
        }

        $exportState = null;

        try {
            $exportState = $this->openExport();
            $exportPath = is_array($exportState) ? (string) ($exportState['path'] ?? '') : '';

            $samples = [];
            $summary = $this->audit->audit($normalizedAreas, function (array $row) use (&$samples, $sampleLimit, &$exportState): void {
                $this->writeExportRow($exportState, $row);

                if ($sampleLimit > 0 && $row['status'] !== MediaPathAuditService::STATUS_VALID && count($samples) < $sampleLimit) {
                    $samples[] = $row;
                }
            });

            $this->closeExport($exportState);
            $exportState = null;

            $this->renderOutput($summary, $samples, $format);

            if ($exportPath !== '') {
                $this->line('report=' . $exportPath);
            }

            return self::SUCCESS;
        } catch (RuntimeException|JsonException $exception) {
            $this->closeExport($exportState);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param array<string,mixed> $summary
     * @param list<array<string,mixed>> $samples
     */
    private function renderOutput(array $summary, array $samples, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode([
                'areas' => $summary['areas'] ?? [],
                'totals' => $summary['totals'] ?? [],
                'targets' => $summary['targets'] ?? [],
                'samples' => $samples,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }

        $targetRows = array_map(function (array $target): array {
            return [
                'target' => sprintf('%s.%s', $target['table'], $target['column']),
                'disk' => (string) ($target['disk'] ?? ''),
                'total' => (int) ($target['total'] ?? 0),
                'valid' => (int) data_get($target, 'statuses.valid', 0),
                'missing' => (int) data_get($target, 'statuses.missing_path', 0),
                'missing_file' => (int) data_get($target, 'statuses.missing_file', 0),
                'legacy' => (int) data_get($target, 'statuses.legacy_local_path', 0),
                'invalid' => (int) data_get($target, 'statuses.invalid_url_or_path', 0),
                'unknown' => (int) data_get($target, 'statuses.unknown', 0),
                'db' => (int) data_get($target, 'problem_domains.db', 0),
                'storage' => (int) data_get($target, 'problem_domains.storage', 0),
                'legacy_issue' => (int) data_get($target, 'problem_domains.legacy_migration', 0),
            ];
        }, (array) ($summary['targets'] ?? []));

        if ($targetRows !== []) {
            $this->table([
                'target',
                'disk',
                'total',
                'valid',
                'missing',
                'missing_file',
                'legacy',
                'invalid',
                'unknown',
                'db',
                'storage',
                'legacy_issue',
            ], $targetRows);
        }

        if ($samples === []) {
            return;
        }

        $sampleRows = array_map(function (array $row): array {
            return [
                'target' => sprintf('%s.%s', $row['table'], $row['column']),
                'id' => (int) ($row['record_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'domain' => (string) ($row['problem_domain'] ?? ''),
                'raw_value' => (string) ($row['raw_value'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }, $samples);

        $this->newLine();
        $this->table(['target', 'id', 'status', 'domain', 'raw_value', 'notes'], $sampleRows);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function openExport(): ?array
    {
        $rawPath = trim((string) $this->option('export'));
        if ($rawPath === '') {
            return null;
        }

        $absolutePath = $this->resolveExportPath($rawPath);
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['json', 'csv'], true)) {
            throw new RuntimeException('Export path must end with .json or .csv');
        }

        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create export directory: %s', $directory));
        }

        $handle = @fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open export file: %s', $absolutePath));
        }

        if ($extension === 'json') {
            fwrite($handle, "[\n");
        } else {
            fputcsv($handle, [
                'area',
                'table',
                'column',
                'record_id',
                'disk',
                'expected_format',
                'raw_value',
                'normalized_path',
                'legacy_candidate',
                'legacy_candidate_exists',
                'status',
                'problem_domain',
                'notes',
                'context',
            ]);
        }

        return [
            'path' => $absolutePath,
            'extension' => $extension,
            'handle' => $handle,
            'first' => true,
        ];
    }

    /**
     * @param array<string,mixed>|null $state
     * @param array<string,mixed> $row
     */
    private function writeExportRow(?array &$state, array $row): void
    {
        if ($state === null) {
            return;
        }

        $handle = $state['handle'] ?? null;
        if (!is_resource($handle)) {
            throw new RuntimeException('Export handle is not available.');
        }

        if (($state['extension'] ?? null) === 'json') {
            $prefix = ($state['first'] ?? true) ? '' : ",\n";
            fwrite($handle, $prefix . json_encode($row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $state['first'] = false;

            return;
        }

        fputcsv($handle, [
            $row['area'] ?? '',
            $row['table'] ?? '',
            $row['column'] ?? '',
            $row['record_id'] ?? '',
            $row['disk'] ?? '',
            $row['expected_format'] ?? '',
            $row['raw_value'] ?? '',
            $row['normalized_path'] ?? '',
            $row['legacy_candidate'] ?? '',
            $row['legacy_candidate_exists'] === null ? '' : (($row['legacy_candidate_exists'] ?? false) ? '1' : '0'),
            $row['status'] ?? '',
            $row['problem_domain'] ?? '',
            $row['notes'] ?? '',
            json_encode($row['context'] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param array<string,mixed>|null $state
     */
    private function closeExport(?array $state): void
    {
        if ($state === null) {
            return;
        }

        $handle = $state['handle'] ?? null;
        if (!is_resource($handle)) {
            return;
        }

        if (($state['extension'] ?? null) === 'json') {
            fwrite($handle, "\n]\n");
        }

        fclose($handle);
    }

    private function resolveExportPath(string $path): string
    {
        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}

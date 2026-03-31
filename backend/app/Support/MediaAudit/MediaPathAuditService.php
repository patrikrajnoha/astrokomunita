<?php

namespace App\Support\MediaAudit;

use App\Services\Storage\MediaStorageService;
use App\Support\BotAvatarResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaPathAuditService
{
    public const STATUS_MISSING_PATH = 'missing_path';
    public const STATUS_VALID = 'valid';
    public const STATUS_MISSING_FILE = 'missing_file';
    public const STATUS_LEGACY_LOCAL_PATH = 'legacy_local_path';
    public const STATUS_INVALID_URL_OR_PATH = 'invalid_url_or_path';
    public const STATUS_UNKNOWN = 'unknown';

    public const DOMAIN_DB = 'db';
    public const DOMAIN_STORAGE = 'storage';
    public const DOMAIN_LEGACY_MIGRATION = 'legacy_migration';
    public const DOMAIN_NONE = 'none';
    public const DOMAIN_UNKNOWN = 'unknown';

    /**
     * @var list<string>
     */
    private const STATUSES = [
        self::STATUS_MISSING_PATH,
        self::STATUS_VALID,
        self::STATUS_MISSING_FILE,
        self::STATUS_LEGACY_LOCAL_PATH,
        self::STATUS_INVALID_URL_OR_PATH,
        self::STATUS_UNKNOWN,
    ];

    /**
     * @var list<string>
     */
    private const DOMAINS = [
        self::DOMAIN_DB,
        self::DOMAIN_STORAGE,
        self::DOMAIN_LEGACY_MIGRATION,
        self::DOMAIN_NONE,
        self::DOMAIN_UNKNOWN,
    ];

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return list<string>
     */
    public function supportedAreas(): array
    {
        return [
            'observations',
            'polls',
            'posts',
            'profiles',
        ];
    }

    /**
     * @param list<string> $areas
     * @param null|callable(array<string, mixed>):void $onRow
     * @return array{
     *   areas:list<string>,
     *   targets:list<array<string,mixed>>,
     *   totals:array<string,mixed>
     * }
     */
    public function audit(array $areas = [], ?callable $onRow = null): array
    {
        $selectedAreas = $this->normalizeAreas($areas);
        $summary = [];

        foreach ($this->targets() as $target) {
            if (!in_array($target['area'], $selectedAreas, true)) {
                continue;
            }

            $targetKey = $this->targetKey($target);
            $summary[$targetKey] = $this->emptySummary($target);

            $query = $this->targetQuery($target);
            $chunkColumn = isset($target['chunk_column']) ? (string) $target['chunk_column'] : 'id';
            $chunkAlias = isset($target['chunk_alias']) ? (string) $target['chunk_alias'] : 'id';
            $query->chunkById(200, function ($rows) use ($target, $targetKey, &$summary, $onRow): void {
                foreach ($rows as $row) {
                    $result = $this->classifyRow($target, $row);

                    $summary[$targetKey]['total']++;
                    $summary[$targetKey]['statuses'][$result['status']]++;
                    $summary[$targetKey]['problem_domains'][$result['problem_domain']]++;

                    if ($onRow !== null) {
                        $onRow($result);
                    }
                }
            }, $chunkColumn, $chunkAlias);
        }

        return [
            'areas' => $selectedAreas,
            'targets' => array_values($summary),
            'totals' => $this->aggregateTotals(array_values($summary)),
        ];
    }

    /**
     * @param list<string> $areas
     * @return list<string>
     */
    public function normalizeAreas(array $areas): array
    {
        $supported = $this->supportedAreas();
        if ($areas === []) {
            return $supported;
        }

        $normalized = [];
        foreach ($areas as $area) {
            $value = strtolower(trim((string) $area));
            if ($value !== '' && in_array($value, $supported, true) && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     * @return array<string,mixed>
     */
    private function classifyRow(array $target, object $row): array
    {
        $column = (string) $target['column'];
        $rawValue = $this->stringValue($row->{$column} ?? null);
        $normalizedPath = $this->normalizePath($rawValue);
        $disk = $this->resolveDisk($target);
        $expectedFormat = $this->expectedFormat($target, $row);
        $required = $this->isRequired($target, $row);
        $context = $this->context($target, $row);

        $result = [
            'area' => $target['area'],
            'table' => $target['table'],
            'column' => $column,
            'record_id' => (int) ($row->id ?? 0),
            'disk' => $disk,
            'expected_format' => $expectedFormat,
            'raw_value' => $rawValue !== '' ? $rawValue : null,
            'normalized_path' => $normalizedPath !== '' ? $normalizedPath : null,
            'legacy_candidate' => null,
            'legacy_candidate_exists' => null,
            'status' => self::STATUS_UNKNOWN,
            'problem_domain' => self::DOMAIN_UNKNOWN,
            'notes' => null,
            'context' => $context,
        ];

        if ($rawValue === '') {
            $result['status'] = self::STATUS_MISSING_PATH;
            $result['problem_domain'] = $required ? self::DOMAIN_DB : self::DOMAIN_NONE;
            $result['notes'] = $required
                ? 'Expected a non-empty stored path.'
                : 'Optional media path is empty.';

            return $result;
        }

        $legacy = $this->detectLegacyLocalPath($rawValue);
        if ($legacy !== null) {
            $result['status'] = self::STATUS_LEGACY_LOCAL_PATH;
            $result['problem_domain'] = self::DOMAIN_LEGACY_MIGRATION;
            $result['legacy_candidate'] = $legacy['candidate'];
            $result['legacy_candidate_exists'] = $legacy['candidate'] !== null
                ? $this->safeExists($target, $row, $legacy['candidate'])
                : null;
            $result['notes'] = $legacy['reason'];

            return $result;
        }

        $invalidReason = $this->detectInvalidPath($target, $row, $rawValue, $normalizedPath);
        if ($invalidReason !== null) {
            $result['status'] = self::STATUS_INVALID_URL_OR_PATH;
            $result['problem_domain'] = self::DOMAIN_DB;
            $result['notes'] = $invalidReason;

            return $result;
        }

        $exists = $this->safeExists($target, $row, $normalizedPath);
        if ($exists === true) {
            $result['status'] = self::STATUS_VALID;
            $result['problem_domain'] = self::DOMAIN_NONE;
            $result['notes'] = 'Resolved path exists on the configured disk.';

            return $result;
        }

        if ($exists === false) {
            $result['status'] = self::STATUS_MISSING_FILE;
            $result['problem_domain'] = self::DOMAIN_STORAGE;
            $result['notes'] = 'Stored path is non-empty but file was not found on the configured disk.';

            return $result;
        }

        $result['status'] = self::STATUS_UNKNOWN;
        $result['problem_domain'] = self::DOMAIN_UNKNOWN;
        $result['notes'] = 'Storage existence check could not be completed.';

        return $result;
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     * @return array<string,mixed>
     */
    private function context(array $target, object $row): array
    {
        return match ($target['key']) {
            'observation_media.path' => [
                'observation_id' => (int) ($row->observation_id ?? 0),
                'mime_type' => $this->nullableString($row->mime_type ?? null),
            ],
            'poll_options.image_path' => [
                'poll_id' => (int) ($row->poll_id ?? 0),
                'post_id' => (int) ($row->post_id ?? 0),
                'text' => $this->nullableString($row->text ?? null),
            ],
            'posts.attachment_path', 'posts.attachment_web_path', 'posts.attachment_original_path' => [
                'post_id' => (int) ($row->id ?? 0),
                'user_id' => (int) ($row->user_id ?? 0),
                'author_kind' => $this->nullableString($row->author_kind ?? null),
                'bot_identity' => $this->nullableString($row->bot_identity ?? null),
                'source_name' => $this->nullableString($row->source_name ?? null),
                'attachment_mime' => $this->nullableString($row->attachment_mime ?? null),
                'attachment_original_mime' => $this->nullableString($row->attachment_original_mime ?? null),
                'attachment_web_mime' => $this->nullableString($row->attachment_web_mime ?? null),
                'attachment_original_name' => $this->nullableString($row->attachment_original_name ?? null),
            ],
            'users.avatar_path', 'users.cover_path' => [
                'user_id' => (int) ($row->id ?? 0),
                'username' => $this->nullableString($row->username ?? null),
                'is_bot' => $this->isBotUser($row),
                'role' => $this->nullableString($row->role ?? null),
                'avatar_mode' => $this->nullableString($row->avatar_mode ?? null),
            ],
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     */
    private function expectedFormat(array $target, object $row): string
    {
        return match ($target['key']) {
            'users.avatar_path' => $this->isBotUser($row)
                ? 'relative_path_or_bots/{username}/{file}.png'
                : 'relative_path',
            default => (string) $target['expected_format'],
        };
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     */
    private function isRequired(array $target, object $row): bool
    {
        return match ($target['key']) {
            'observation_media.path' => true,
            'posts.attachment_web_path' => $this->postExpectsWebVariant($row),
            'posts.attachment_original_path' => $this->postExpectsOriginalVariant($row),
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $target
     */
    private function resolveDisk(array $target): string
    {
        return match ($target['disk']) {
            'public' => $this->mediaStorage->publicDiskName(),
            'private' => $this->mediaStorage->privateDiskName(),
            default => (string) $target['disk'],
        };
    }

    private function safeExists(array $target, object $row, string $path): ?bool
    {
        try {
            return $this->pathExists($target, $row, $path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     */
    private function pathExists(array $target, object $row, string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === '') {
            return false;
        }

        if ($target['key'] === 'users.avatar_path' && $this->isBotUser($row)) {
            $username = strtolower(trim((string) ($row->username ?? '')));
            if (BotAvatarResolver::isBotAssetPath($normalizedPath, $username)) {
                return BotAvatarResolver::isValidBotAvatarPath($normalizedPath, $username);
            }
        }

        return Storage::disk($this->resolveDisk($target))->exists($normalizedPath);
    }

    /**
     * @param array<string,mixed> $target
     * @param object $row
     */
    private function detectInvalidPath(array $target, object $row, string $rawValue, string $normalizedPath): ?string
    {
        if ($normalizedPath === '') {
            return 'Path normalizes to an empty value.';
        }

        if (str_contains($normalizedPath, '..')) {
            return 'Path contains traversal markers.';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $rawValue) === 1) {
            return 'Absolute URL is stored where a storage-relative path is expected.';
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $rawValue) === 1) {
            return 'Filesystem absolute path is stored where a storage-relative path is expected.';
        }

        if (str_starts_with($rawValue, '/api/media/file/') || str_starts_with($normalizedPath, 'api/media/file/')) {
            return 'API route is stored instead of a storage-relative path.';
        }

        if (str_starts_with($rawValue, '/assets/') || str_starts_with($normalizedPath, 'assets/')) {
            return 'Public asset URL/path is stored instead of a storage-relative path.';
        }

        if (str_starts_with($rawValue, '/')) {
            return 'Root-relative path is stored where a storage-relative path is expected.';
        }

        if (str_contains($normalizedPath, '?') || str_contains($normalizedPath, '#')) {
            return 'Path contains URL query or fragment components.';
        }

        if ($target['key'] === 'users.avatar_path') {
            $isBot = $this->isBotUser($row);
            $username = strtolower(trim((string) ($row->username ?? '')));

            if (!$isBot && BotAvatarResolver::isBotAssetPath($normalizedPath)) {
                return 'Non-bot user avatar points to bot asset namespace.';
            }

            if ($isBot && BotAvatarResolver::isBotAssetPath($normalizedPath) && !BotAvatarResolver::isBotAssetPath($normalizedPath, $username)) {
                return 'Bot avatar path points to a different bot asset namespace.';
            }
        }

        return null;
    }

    /**
     * @return array{candidate:?string,reason:string}|null
     */
    private function detectLegacyLocalPath(string $rawValue): ?array
    {
        $value = trim($rawValue);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $value);
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;

        if (preg_match('#^https?://#i', $normalized) === 1) {
            $path = (string) (parse_url($normalized, PHP_URL_PATH) ?: '');
            if (preg_match('#^/storage/(.+)$#i', $path, $matches) === 1) {
                return [
                    'candidate' => $this->normalizePath($matches[1]),
                    'reason' => 'Legacy app /storage URL is stored in DB instead of a storage-relative path.',
                ];
            }
        }

        $prefixes = [
            '/storage/' => 'Legacy /storage path is stored in DB instead of a storage-relative path.',
            'storage/' => 'Legacy storage/ path is stored in DB instead of a storage-relative path.',
            'public/' => 'Legacy public/ path is stored in DB instead of a storage-relative path.',
            'app/public/' => 'Legacy app/public/ path is stored in DB instead of a storage-relative path.',
            'storage/app/public/' => 'Legacy storage/app/public/ path is stored in DB instead of a storage-relative path.',
        ];

        foreach ($prefixes as $prefix => $reason) {
            if (!str_starts_with(strtolower($normalized), strtolower($prefix))) {
                continue;
            }

            $candidate = $this->normalizePath(substr($normalized, strlen($prefix)));
            if ($candidate === '') {
                $candidate = null;
            }

            return [
                'candidate' => $candidate,
                'reason' => $reason,
            ];
        }

        if (preg_match('#/storage/app/public/(.+)$#i', $normalized, $matches) === 1) {
            return [
                'candidate' => $this->normalizePath($matches[1]),
                'reason' => 'Legacy absolute storage/app/public path is stored in DB instead of a storage-relative path.',
            ];
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $value) === 1 || preg_match('#^/(var|srv|home|tmp)/#i', $normalized) === 1) {
            return [
                'candidate' => null,
                'reason' => 'Legacy filesystem absolute path is stored in DB instead of a storage-relative path.',
            ];
        }

        return null;
    }

    private function normalizePath(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;
        $normalized = ltrim($normalized, '/');

        return trim($normalized);
    }

    private function postExpectsWebVariant(object $row): bool
    {
        return $this->normalizePath((string) ($row->attachment_original_path ?? '')) !== ''
            || $this->nullableString($row->attachment_variants_json ?? null) !== null
            || $this->nullableString($row->attachment_web_mime ?? null) !== null
            || $row->attachment_web_size !== null
            || $row->attachment_web_width !== null
            || $row->attachment_web_height !== null;
    }

    private function postExpectsOriginalVariant(object $row): bool
    {
        return $this->nullableString($row->attachment_original_mime ?? null) !== null
            || $row->attachment_original_size !== null
            || $this->nullableString($row->attachment_variants_json ?? null) !== null;
    }

    private function isBotUser(object $row): bool
    {
        return (bool) ($row->is_bot ?? false) || strtolower(trim((string) ($row->role ?? ''))) === 'bot';
    }

    /**
     * @param array<string,mixed> $target
     */
    private function targetKey(array $target): string
    {
        return (string) $target['key'];
    }

    /**
     * @param array<string,mixed> $target
     * @return Builder
     */
    private function targetQuery(array $target): Builder
    {
        $query = $target['query']();
        if (!$query instanceof Builder) {
            throw new RuntimeException(sprintf('Target query for %s must return a query builder.', $target['key']));
        }

        return $query;
    }

    /**
     * @param array<string,mixed> $target
     * @return array<string,mixed>
     */
    private function emptySummary(array $target): array
    {
        $statuses = [];
        foreach (self::STATUSES as $status) {
            $statuses[$status] = 0;
        }

        $domains = [];
        foreach (self::DOMAINS as $domain) {
            $domains[$domain] = 0;
        }

        return [
            'area' => $target['area'],
            'table' => $target['table'],
            'column' => $target['column'],
            'disk' => $this->resolveDisk($target),
            'expected_format' => (string) $target['expected_format'],
            'total' => 0,
            'statuses' => $statuses,
            'problem_domains' => $domains,
        ];
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return array<string,mixed>
     */
    private function aggregateTotals(array $summaries): array
    {
        $totals = [
            'targets' => count($summaries),
            'rows' => 0,
            'statuses' => array_fill_keys(self::STATUSES, 0),
            'problem_domains' => array_fill_keys(self::DOMAINS, 0),
        ];

        foreach ($summaries as $summary) {
            $totals['rows'] += (int) ($summary['total'] ?? 0);

            foreach (self::STATUSES as $status) {
                $totals['statuses'][$status] += (int) ($summary['statuses'][$status] ?? 0);
            }

            foreach (self::DOMAINS as $domain) {
                $totals['problem_domains'][$domain] += (int) ($summary['problem_domains'][$domain] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function targets(): array
    {
        return [
            [
                'key' => 'observation_media.path',
                'area' => 'observations',
                'table' => 'observation_media',
                'column' => 'path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => DB::table('observation_media')
                    ->select(['id', 'observation_id', 'path', 'mime_type'])
                    ->orderBy('id'),
            ],
            [
                'key' => 'poll_options.image_path',
                'area' => 'polls',
                'table' => 'poll_options',
                'column' => 'image_path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'chunk_column' => 'poll_options.id',
                'chunk_alias' => 'id',
                'query' => fn (): Builder => DB::table('poll_options')
                    ->leftJoin('polls', 'polls.id', '=', 'poll_options.poll_id')
                    ->select([
                        'poll_options.id',
                        'poll_options.poll_id',
                        'poll_options.text',
                        'poll_options.image_path',
                        'polls.post_id',
                    ])
                    ->orderBy('poll_options.id'),
            ],
            [
                'key' => 'posts.attachment_path',
                'area' => 'posts',
                'table' => 'posts',
                'column' => 'attachment_path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => $this->postAuditQuery(),
            ],
            [
                'key' => 'posts.attachment_web_path',
                'area' => 'posts',
                'table' => 'posts',
                'column' => 'attachment_web_path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => $this->postAuditQuery(),
            ],
            [
                'key' => 'posts.attachment_original_path',
                'area' => 'posts',
                'table' => 'posts',
                'column' => 'attachment_original_path',
                'disk' => 'private',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => $this->postAuditQuery(),
            ],
            [
                'key' => 'users.avatar_path',
                'area' => 'profiles',
                'table' => 'users',
                'column' => 'avatar_path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => DB::table('users')
                    ->select(['id', 'username', 'is_bot', 'role', 'avatar_mode', 'avatar_path'])
                    ->orderBy('id'),
            ],
            [
                'key' => 'users.cover_path',
                'area' => 'profiles',
                'table' => 'users',
                'column' => 'cover_path',
                'disk' => 'public',
                'expected_format' => 'relative_path',
                'query' => fn (): Builder => DB::table('users')
                    ->select(['id', 'username', 'is_bot', 'role', 'avatar_mode', 'cover_path'])
                    ->orderBy('id'),
            ],
        ];
    }

    private function postAuditQuery(): Builder
    {
        return DB::table('posts')
            ->select([
                'id',
                'user_id',
                'author_kind',
                'bot_identity',
                'source_name',
                'attachment_path',
                'attachment_web_path',
                'attachment_original_path',
                'attachment_mime',
                'attachment_original_mime',
                'attachment_web_mime',
                'attachment_original_name',
                'attachment_original_size',
                'attachment_web_size',
                'attachment_web_width',
                'attachment_web_height',
                'attachment_variants_json',
            ])
            ->orderBy('id');
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}

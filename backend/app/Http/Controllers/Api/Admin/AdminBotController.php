<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Services\Bots\BotRunner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AdminBotController extends Controller
{
    public function __construct(
        private readonly BotRunner $runner,
    ) {
    }

    public function sources(): JsonResponse
    {
        $sources = BotSource::query()
            ->orderBy('key')
            ->get();

        $data = $sources->map(fn (BotSource $source): array => [
            'id' => $source->id,
            'key' => (string) $source->key,
            'bot_identity' => $source->bot_identity?->value ?? (string) $source->bot_identity,
            'source_type' => $source->source_type?->value ?? (string) $source->source_type,
            'url' => (string) $source->url,
            'is_enabled' => (bool) $source->is_enabled,
            'last_run_at' => $source->last_run_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourceKey' => 'nullable|string|max:120',
            'bot_identity' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = BotRun::query()
            ->with('source:id,key');

        $sourceKey = strtolower(trim((string) ($validated['sourceKey'] ?? '')));
        if ($sourceKey !== '') {
            $query->whereHas('source', function ($sourceQuery) use ($sourceKey): void {
                $sourceQuery->where('key', $sourceKey);
            });
        }

        $botIdentity = strtolower(trim((string) ($validated['bot_identity'] ?? '')));
        if ($botIdentity !== '') {
            $query->where('bot_identity', $botIdentity);
        }

        $status = strtolower(trim((string) ($validated['status'] ?? '')));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('started_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        $dateTo = trim((string) ($validated['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('started_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotRun $run): array => $this->serializeRun($run))
        );

        return response()->json($paginator);
    }

    public function run(Request $request, string $sourceKey): JsonResponse
    {
        $normalizedSourceKey = strtolower(trim($sourceKey));

        $source = BotSource::query()
            ->where('key', $normalizedSourceKey)
            ->first();

        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        if (!$source->is_enabled) {
            return response()->json([
                'message' => sprintf('Bot source "%s" is disabled.', $normalizedSourceKey),
            ], 422);
        }

        $throttleSeconds = 120;
        $throttleKey = sprintf('bots:throttle:manual:%s', $normalizedSourceKey);
        $throttleExpiresAt = now()->addSeconds($throttleSeconds)->timestamp;
        if (!Cache::add($throttleKey, $throttleExpiresAt, $throttleSeconds)) {
            $retryAfter = max(1, (int) Cache::get($throttleKey, 0) - now()->timestamp);

            return response()->json([
                'message' => sprintf('Manual run for "%s" is temporarily throttled.', $normalizedSourceKey),
                'retry_after' => $retryAfter,
            ], 429);
        }

        $run = $this->runner->run(
            $source,
            'manual',
            $request->boolean('force_manual_override')
        );

        return response()->json([
            'run_id' => $run->id,
            'source_key' => $source->key,
            'status' => $run->status?->value ?? (string) $run->status,
            'stats' => is_array($run->stats) ? $run->stats : [],
            'error_text' => $this->truncateErrorText($run->error_text),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
            'sourceKey' => 'nullable|string|max:120|required_without:run_id',
            'date' => 'nullable|date_format:Y-m-d|required_with:sourceKey|required_without:run_id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;
        $sourceId = null;
        $windowStart = null;
        $windowEnd = null;

        if ($runId !== null) {
            $run = BotRun::query()->find($runId);
            if (!$run) {
                throw ValidationException::withMessages([
                    'run_id' => 'Selected run_id is invalid.',
                ]);
            }

            $sourceId = (int) $run->source_id;
            [$windowStart, $windowEnd] = $this->resolveRunWindow($run);
        } else {
            $sourceKey = strtolower(trim((string) ($validated['sourceKey'] ?? '')));
            $date = trim((string) ($validated['date'] ?? ''));

            if ($sourceKey === '' || $date === '') {
                throw ValidationException::withMessages([
                    'run_id' => 'Provide run_id or sourceKey and date.',
                ]);
            }

            $source = BotSource::query()->where('key', $sourceKey)->first();
            if (!$source) {
                return response()->json([
                    'message' => sprintf('Bot source "%s" was not found.', $sourceKey),
                ], 404);
            }

            $sourceId = (int) $source->id;
            $dateStart = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            $windowStart = $dateStart->copy();
            $windowEnd = $dateStart->copy()->endOfDay();
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $query = BotItem::query()
            ->where('source_id', $sourceId)
            ->whereBetween('fetched_at', [$windowStart, $windowEnd])
            ->orderByDesc('fetched_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotItem $item): array => $this->serializeItem($item))
        );

        return response()->json($paginator);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeRun(BotRun $run): array
    {
        return [
            'id' => $run->id,
            'source_id' => $run->source_id,
            'source_key' => $run->source?->key,
            'bot_identity' => $run->bot_identity?->value ?? (string) $run->bot_identity,
            'status' => $run->status?->value ?? (string) $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'stats' => is_array($run->stats) ? $run->stats : [],
            'error_text' => $this->truncateErrorText($run->error_text),
        ];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRunWindow(BotRun $run): array
    {
        $start = ($run->started_at?->copy() ?? now())->subMinutes(2);
        $end = ($run->finished_at?->copy() ?? now())->addMinutes(2);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeItem(BotItem $item): array
    {
        $meta = is_array($item->meta) ? $item->meta : [];

        $titleOriginal = trim((string) $item->title);
        $titleTranslated = trim((string) $item->title_translated);
        $resolvedTitle = $titleTranslated !== '' ? $titleTranslated : $titleOriginal;

        return [
            'id' => $item->id,
            'stable_key' => (string) $item->stable_key,
            'publish_status' => $item->publish_status?->value ?? (string) $item->publish_status,
            'translation_status' => $item->translation_status?->value ?? (string) $item->translation_status,
            'post_id' => $item->post_id,
            'url' => $item->url,
            'title' => $resolvedTitle,
            'title_original' => $titleOriginal,
            'title_translated' => $titleTranslated !== '' ? $titleTranslated : null,
            'fetched_at' => $item->fetched_at?->toIso8601String(),
            'published_at' => $item->published_at?->toIso8601String(),
            'skip_reason' => $this->nullableString(data_get($meta, 'skip_reason')),
            'used_translation' => $this->nullableBool(data_get($meta, 'used_translation')),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    private function truncateErrorText(?string $value, int $maxLength = 1000): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($maxLength <= 0) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\BotSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ManagesBotSources
{
    public function sources(Request $request): JsonResponse
    {
        $this->botSourceSyncService->syncDefaults();

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'failing_only' => 'nullable|boolean',
            'q' => 'nullable|string|max:120',
        ]);

        $query = BotSource::query();

        if (array_key_exists('enabled', $validated)) {
            $query->where('is_enabled', (bool) $validated['enabled']);
        }
        if (($validated['failing_only'] ?? false) === true) {
            $query->where('consecutive_failures', '>', 0);
        }

        $search = strtolower(trim((string) ($validated['q'] ?? '')));
        if ($search !== '') {
            $query->where(function (Builder $sourceQuery) use ($search): void {
                $sourceQuery
                    ->whereRaw('LOWER(COALESCE(name, "")) like ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(key) like ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(url) like ?', ["%{$search}%"]);
            });
        }

        $sources = $query
            ->orderBy('key')
            ->get();

        $sourceMetrics = $this->sourceMetricsBySourceId($sources);
        $data = $sources->map(fn (BotSource $source): array => $this->serializeSource(
            $source,
            $sourceMetrics[$source->id] ?? []
        ))->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function updateSource(Request $request, int $sourceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:160',
            'url' => 'sometimes|string|max:2048|url',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenašiel.',
            ], 404);
        }

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $this->nullableString($validated['name']);
        }
        if (array_key_exists('url', $validated)) {
            $updates['url'] = trim((string) $validated['url']);
        }
        if (array_key_exists('is_enabled', $validated)) {
            $updates['is_enabled'] = (bool) $validated['is_enabled'];
        }

        if ($updates !== []) {
            $source->fill($updates)->save();
        }

        $metrics = $this->sourceMetricsBySourceId(collect([$source->fresh() ?? $source]));

        return response()->json([
            'data' => $this->serializeSource($source->fresh() ?? $source, $metrics[$source->id] ?? []),
        ]);
    }

    public function resetSourceHealth(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenašiel.',
            ], 404);
        }

        $this->botSourceHealthService->resetHealth($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }

    public function clearSourceCooldown(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenašiel.',
            ], 404);
        }

        $this->botSourceHealthService->clearCooldown($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }

    public function reviveSource(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenašiel.',
            ], 404);
        }

        $this->botSourceHealthService->revive($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }
}

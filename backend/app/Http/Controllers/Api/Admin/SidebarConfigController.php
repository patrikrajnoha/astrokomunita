<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SidebarSectionConfig;
use App\Support\SidebarSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SidebarConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope', SidebarSectionRegistry::SCOPE_HOME);

        if (!SidebarSectionRegistry::isValidScope($scope)) {
            return response()->json([
                'message' => 'Invalid sidebar scope.',
            ], 400);
        }

        return response()->json([
            'scope' => $scope,
            'data' => $this->buildConfig($scope),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $scope = $request->query('scope');

        if (!SidebarSectionRegistry::isValidScope($scope)) {
            return response()->json([
                'message' => 'Invalid sidebar scope.',
            ], 400);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.section_key' => ['required', 'string'],
            'items.*.order' => ['required', 'integer', 'min:0'],
            'items.*.is_enabled' => ['required', 'boolean'],
        ]);

        $rawItems = $validated['items'];
        $knownKeys = array_map(static fn (array $section) => $section['section_key'], SidebarSectionRegistry::sections());

        foreach ($rawItems as $item) {
            if (!SidebarSectionRegistry::isValidSectionKey($item['section_key'])) {
                return response()->json([
                    'message' => 'Unknown section_key provided.',
                    'section_key' => $item['section_key'],
                ], 400);
            }
        }

        $itemsByKey = [];
        foreach ($rawItems as $item) {
            $itemsByKey[$item['section_key']] = [
                'section_key' => $item['section_key'],
                'order' => (int) $item['order'],
                'is_enabled' => (bool) $item['is_enabled'],
            ];
        }

        foreach ($knownKeys as $defaultOrder => $sectionKey) {
            if (isset($itemsByKey[$sectionKey])) {
                continue;
            }

            $registrySection = SidebarSectionRegistry::sectionByKey($sectionKey);
            $itemsByKey[$sectionKey] = [
                'section_key' => $sectionKey,
                'order' => (int) ($registrySection['default_order'] ?? $defaultOrder),
                'is_enabled' => (bool) ($registrySection['default_enabled'] ?? true),
            ];
        }

        $normalized = array_values($itemsByKey);
        usort($normalized, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        foreach ($normalized as $index => $item) {
            $normalized[$index]['order'] = $index;
        }

        DB::transaction(function () use ($scope, $normalized): void {
            foreach ($normalized as $item) {
                SidebarSectionConfig::query()->updateOrCreate(
                    [
                        'scope' => $scope,
                        'section_key' => $item['section_key'],
                    ],
                    [
                        'order' => $item['order'],
                        'is_enabled' => $item['is_enabled'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Sidebar configuration updated.',
            'scope' => $scope,
            'data' => $this->buildConfig($scope),
        ]);
    }

    /**
     * @return array<int, array{section_key:string,title:string,order:int,is_enabled:bool}>
     */
    private function buildConfig(string $scope): array
    {
        $dbRows = SidebarSectionConfig::query()
            ->forScope($scope)
            ->get()
            ->keyBy('section_key');

        $items = [];

        foreach (SidebarSectionRegistry::sections() as $section) {
            $dbRow = $dbRows->get($section['section_key']);

            $items[] = [
                'section_key' => $section['section_key'],
                'title' => $section['title'],
                'order' => $dbRow ? (int) $dbRow->order : (int) $section['default_order'],
                'is_enabled' => $dbRow ? (bool) $dbRow->is_enabled : (bool) $section['default_enabled'],
            ];
        }

        usort($items, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($items);
    }
}

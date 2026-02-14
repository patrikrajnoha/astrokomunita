<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SidebarCustomComponentPayload;
use App\Models\SidebarSectionConfig;
use App\Support\SidebarSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildConfig(string $scope): array
    {
        $dbRows = SidebarSectionConfig::query()
            ->forScope($scope)
            ->with('customComponent')
            ->get()
            ->sortBy('order')
            ->values();

        $items = [];
        $builtinRows = $dbRows->where('kind', 'builtin')->keyBy('section_key');

        foreach (SidebarSectionRegistry::sections() as $section) {
            $dbRow = $builtinRows->get($section['section_key']);

            $items[] = [
                'kind' => 'builtin',
                'section_key' => $section['section_key'],
                'title' => $section['title'],
                'custom_component_id' => null,
                'custom_component' => null,
                'order' => $dbRow ? (int) $dbRow->order : (int) $section['default_order'],
                'is_enabled' => $dbRow ? (bool) $dbRow->is_enabled : (bool) $section['default_enabled'],
            ];
        }

        foreach ($dbRows->where('kind', 'custom_component') as $dbRow) {
            $component = $dbRow->customComponent;

            $items[] = [
                'kind' => 'custom_component',
                'section_key' => 'custom_component',
                'title' => $component ? $component->name : 'Custom component (deleted)',
                'custom_component_id' => $component ? (int) $component->id : (int) ($dbRow->custom_component_id ?? 0),
                'custom_component' => $component ? SidebarCustomComponentPayload::toArray($component) : null,
                'order' => (int) $dbRow->order,
                'is_enabled' => (bool) $dbRow->is_enabled,
            ];
        }

        usort($items, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($items);
    }
}

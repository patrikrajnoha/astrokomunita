<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

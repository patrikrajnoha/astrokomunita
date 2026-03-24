<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SidebarSectionConfig;
use App\Support\SidebarSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSidebarConfigController extends Controller
{
    private const SCOPE = SidebarSectionRegistry::SCOPE_HOME;
    private const MAX_ENABLED_WIDGETS = 3;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->buildConfig(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.section_key' => ['required', 'string'],
            'sections.*.is_enabled' => ['required', 'boolean'],
            'sections.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $enabledCount = collect($validated['sections'])
            ->filter(static fn (array $section): bool => (bool) ($section['is_enabled'] ?? false))
            ->count();

        if ($enabledCount > self::MAX_ENABLED_WIDGETS) {
            throw ValidationException::withMessages([
                'sections' => sprintf('Mozu byt aktivne najviac %d widgety.', self::MAX_ENABLED_WIDGETS),
            ]);
        }

        $validSectionKeys = collect(SidebarSectionRegistry::sections())
            ->pluck('section_key')
            ->flip();

        SidebarSectionConfig::query()
            ->forScope(self::SCOPE)
            ->where('kind', 'builtin')
            ->delete();

        foreach ($validated['sections'] as $sectionData) {
            $sectionKey = (string) ($sectionData['section_key'] ?? '');
            if (! $validSectionKeys->has($sectionKey)) {
                continue;
            }

            SidebarSectionConfig::create([
                'scope' => self::SCOPE,
                'kind' => 'builtin',
                'section_key' => $sectionKey,
                'order' => (int) $sectionData['order'],
                'is_enabled' => (bool) $sectionData['is_enabled'],
            ]);
        }

        return response()->json([
            'data' => $this->buildConfig(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildConfig(): array
    {
        $dbRows = SidebarSectionConfig::query()
            ->forScope(self::SCOPE)
            ->where('kind', 'builtin')
            ->get()
            ->keyBy('section_key');

        $items = [];

        foreach (SidebarSectionRegistry::sections() as $section) {
            $dbRow = $dbRows->get($section['section_key']);

            $items[] = [
                'kind' => 'builtin',
                'section_key' => $section['section_key'],
                'title' => $section['title'],
                'order' => $dbRow ? (int) $dbRow->order : (int) $section['default_order'],
                'is_enabled' => $dbRow ? (bool) $dbRow->is_enabled : false,
            ];
        }

        usort($items, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($items);
    }
}

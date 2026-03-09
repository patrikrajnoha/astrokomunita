<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SidebarCustomComponent;
use App\Models\SidebarSectionConfig;
use App\Support\SidebarCustomComponentsTableGuard;
use App\Support\SidebarCustomComponentPayload;
use App\Support\SidebarSectionRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SidebarConfigController extends Controller
{
    private const MAX_ENABLED_WIDGETS = 3;

    public function index(Request $request): JsonResponse
    {
        if (app()->environment(['local', 'staging'])) {
            Log::debug('Sidebar config request', [
                'scope' => $request->query('scope'),
                'user_id' => $request->user()?->id,
            ]);
        }

        $requestedScope = $request->query('scope');
        $scope = SidebarSectionRegistry::isValidScope($requestedScope)
            ? $requestedScope
            : SidebarSectionRegistry::SCOPE_HOME;

        try {
            return response()->json([
                'scope' => $scope,
                'data' => $this->buildConfig($scope),
                'available_custom_components' => SidebarCustomComponent::query()
                    ->active()
                    ->orderBy('name')
                    ->get()
                    ->map(static fn (SidebarCustomComponent $component) => SidebarCustomComponentPayload::toArray($component))
                    ->values(),
            ]);
        } catch (QueryException $exception) {
            if (SidebarCustomComponentsTableGuard::isMissingTable($exception)) {
                return SidebarCustomComponentsTableGuard::missingTableResponse();
            }

            throw $exception;
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validatedScope = validator(
            ['scope' => $request->query('scope')],
            ['scope' => ['required', 'string', Rule::in(SidebarSectionRegistry::scopes())]]
        )->validate();

        $scope = $validatedScope['scope'];

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['required', Rule::in(['builtin', 'custom_component'])],
            'items.*.section_key' => ['nullable', 'string'],
            'items.*.custom_component_id' => ['nullable', 'integer'],
            'items.*.order' => ['required', 'integer', 'min:0'],
            'items.*.is_enabled' => ['required', 'boolean'],
        ]);

        $rawItems = $validated['items'];
        $itemsByIdentity = [];
        $validSectionKeys = array_map(
            static fn (array $section) => $section['section_key'],
            SidebarSectionRegistry::sections()
        );

        foreach ($rawItems as $index => $item) {
            if (($item['kind'] ?? null) === 'builtin') {
                $sectionKey = $item['section_key'] ?? null;
                if (!is_string($sectionKey) || !in_array($sectionKey, $validSectionKeys, true)) {
                    throw ValidationException::withMessages([
                        "items.$index.section_key" => ['The selected section key is invalid.'],
                    ]);
                }
            } elseif (($item['kind'] ?? null) === 'custom_component') {
                $componentId = $item['custom_component_id'] ?? null;
                if (!is_int($componentId) && !ctype_digit((string) $componentId)) {
                    throw ValidationException::withMessages([
                        "items.$index.custom_component_id" => ['The custom component id field is required.'],
                    ]);
                }

                $componentExists = SidebarCustomComponent::query()
                    ->whereKey((int) $componentId)
                    ->exists();

                if (!$componentExists) {
                    throw ValidationException::withMessages([
                        "items.$index.custom_component_id" => ['The selected custom component id is invalid.'],
                    ]);
                }
            }

            $identity = ($item['kind'] === 'builtin')
                ? 'builtin:'.$item['section_key']
                : 'custom:'.$item['custom_component_id'];

            $itemsByIdentity[$identity] = [
                'kind' => $item['kind'],
                'section_key' => $item['kind'] === 'builtin' ? (string) $item['section_key'] : 'custom_component',
                'custom_component_id' => $item['kind'] === 'custom_component' ? (int) $item['custom_component_id'] : null,
                'order' => (int) $item['order'],
                'is_enabled' => (bool) $item['is_enabled'],
            ];
        }

        $knownKeys = array_map(static fn (array $section) => $section['section_key'], SidebarSectionRegistry::sections());
        foreach ($knownKeys as $defaultOrder => $sectionKey) {
            $identity = 'builtin:'.$sectionKey;
            if (isset($itemsByIdentity[$identity])) {
                continue;
            }

            $registrySection = SidebarSectionRegistry::sectionByKey($sectionKey);
            $itemsByIdentity[$identity] = [
                'kind' => 'builtin',
                'section_key' => $sectionKey,
                'custom_component_id' => null,
                'order' => (int) ($registrySection['default_order'] ?? $defaultOrder),
                'is_enabled' => false,
            ];
        }

        $normalized = array_values($itemsByIdentity);
        usort($normalized, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        foreach ($normalized as $index => $item) {
            $normalized[$index]['order'] = $index;
        }

        $enabledItems = array_values(array_filter(
            $normalized,
            static fn (array $item): bool => (bool) ($item['is_enabled'] ?? false)
        ));

        if (count($enabledItems) > self::MAX_ENABLED_WIDGETS) {
            throw ValidationException::withMessages([
                'items' => ['Sidebar moze mat aktivne maximalne 3 widgety.'],
            ]);
        }

        DB::transaction(function () use ($scope, $normalized): void {
            SidebarSectionConfig::query()->forScope($scope)->delete();

            foreach ($normalized as $item) {
                SidebarSectionConfig::query()->create([
                    'scope' => $scope,
                    'kind' => $item['kind'],
                    'section_key' => $item['section_key'],
                    'custom_component_id' => $item['custom_component_id'],
                    'order' => $item['order'],
                    'is_enabled' => $item['is_enabled'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Konfiguracia sidebaru bola aktualizovana.',
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


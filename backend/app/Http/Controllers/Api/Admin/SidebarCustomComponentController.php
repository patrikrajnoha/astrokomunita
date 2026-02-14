<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSidebarCustomComponentRequest;
use App\Http\Requests\Admin\UpdateSidebarCustomComponentRequest;
use App\Models\SidebarCustomComponent;
use App\Support\SidebarCustomComponentsTableGuard;
use App\Support\SidebarCustomComponentPayload;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarCustomComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', false);

            $query = SidebarCustomComponent::query()->orderByDesc('updated_at')->orderByDesc('id');
            if ($activeOnly) {
                $query->active();
            }

            $items = $query->get()->map(
                static fn (SidebarCustomComponent $component) => SidebarCustomComponentPayload::toArray($component)
            )->values();

            return response()->json([
                'data' => $items,
            ]);
        } catch (QueryException $exception) {
            if (SidebarCustomComponentsTableGuard::isMissingTable($exception)) {
                return SidebarCustomComponentsTableGuard::missingTableResponse();
            }

            throw $exception;
        }
    }

    public function store(StoreSidebarCustomComponentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $component = SidebarCustomComponent::query()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config_json' => $validated['config_json'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Custom component created.',
            'data' => SidebarCustomComponentPayload::toArray($component),
        ], 201);
    }

    public function show(SidebarCustomComponent $component): JsonResponse
    {
        return response()->json([
            'data' => SidebarCustomComponentPayload::toArray($component),
        ]);
    }

    public function update(UpdateSidebarCustomComponentRequest $request, SidebarCustomComponent $component): JsonResponse
    {
        $validated = $request->validated();

        $component->fill([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config_json' => $validated['config_json'],
            'is_active' => (bool) ($validated['is_active'] ?? $component->is_active),
        ])->save();

        return response()->json([
            'message' => 'Custom component updated.',
            'data' => SidebarCustomComponentPayload::toArray($component->fresh()),
        ]);
    }

    public function destroy(SidebarCustomComponent $component): JsonResponse
    {
        $component->delete();

        return response()->json([
            'message' => 'Custom component deleted.',
        ]);
    }
}

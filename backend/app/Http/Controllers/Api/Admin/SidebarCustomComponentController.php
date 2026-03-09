<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSidebarCustomComponentRequest;
use App\Http\Requests\Admin\UpdateSidebarCustomComponentRequest;
use App\Models\SidebarCustomComponent;
use App\Support\SidebarCustomComponentsTableGuard;
use App\Support\SidebarCustomComponentPayload;
use App\Support\SidebarWidgetConfigSchema;
use App\Services\Storage\MediaStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarCustomComponentController extends Controller
{
    private const CONTEST_IMAGE_UPLOAD_MAX_KB = 8192;

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

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
        $normalizedType = SidebarWidgetConfigSchema::normalizeType($validated['type'] ?? null);
        $normalizedConfig = SidebarWidgetConfigSchema::normalizeConfig(
            $normalizedType,
            is_array($validated['config_json'] ?? null) ? $validated['config_json'] : []
        );

        $component = SidebarCustomComponent::query()->create([
            'name' => $validated['name'],
            'type' => $normalizedType,
            'config_json' => $normalizedConfig,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Vlastna komponenta bola vytvorena.',
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
        $normalizedType = SidebarWidgetConfigSchema::normalizeType($validated['type'] ?? null);
        $normalizedConfig = SidebarWidgetConfigSchema::normalizeConfig(
            $normalizedType,
            is_array($validated['config_json'] ?? null) ? $validated['config_json'] : []
        );

        $component->fill([
            'name' => $validated['name'],
            'type' => $normalizedType,
            'config_json' => $normalizedConfig,
            'is_active' => (bool) ($validated['is_active'] ?? $component->is_active),
        ])->save();

        return response()->json([
            'message' => 'Vlastna komponenta bola aktualizovana.',
            'data' => SidebarCustomComponentPayload::toArray($component->fresh()),
        ]);
    }

    public function destroy(SidebarCustomComponent $component): JsonResponse
    {
        $component->delete();

        return response()->json([
            'message' => 'Vlastna komponenta bola vymazana.',
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::CONTEST_IMAGE_UPLOAD_MAX_KB],
        ]);

        $path = $this->mediaStorage->storeSidebarWidgetImage(
            $request->file('file'),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Obrazok bol nahrany.',
            'data' => [
                'path' => $path,
                'url' => $this->mediaStorage->absoluteUrl($path),
            ],
        ], 201);
    }
}


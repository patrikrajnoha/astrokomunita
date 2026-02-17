<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyFeaturedEvent;
use App\Services\MarkYourCalendarPopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeaturedEventController extends Controller
{
    public function __construct(
        private readonly MarkYourCalendarPopupService $popupService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->popupService->adminFeaturedEvents(),
            'settings' => $this->popupService->settingsPayload(),
            'meta' => [
                'max_items' => MarkYourCalendarPopupService::MAX_ACTIVE_ITEMS,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = $this->popupService->createFeaturedEvent(
            $request->user(),
            (int) $validated['event_id'],
            isset($validated['position']) ? (int) $validated['position'] : null
        );

        return response()->json([
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, MonthlyFeaturedEvent $featuredEvent): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item = $this->popupService->updateFeaturedEvent($featuredEvent, $validated);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function destroy(MonthlyFeaturedEvent $featuredEvent): JsonResponse
    {
        $this->popupService->deleteFeaturedEvent($featuredEvent);

        return response()->json([
            'ok' => true,
        ]);
    }

    public function forcePopup(): JsonResponse
    {
        return response()->json($this->popupService->forceShowNow());
    }

    public function updatePopupSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->popupService->updateEnabled((bool) $validated['enabled']),
        ]);
    }
}


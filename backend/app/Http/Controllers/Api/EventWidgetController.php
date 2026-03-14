<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Widgets\EventWidgetService;
use Illuminate\Http\JsonResponse;

class EventWidgetController extends Controller
{
    public function __construct(
        private readonly EventWidgetService $eventWidgetService,
    ) {
    }

    public function upcoming(): JsonResponse
    {
        return response()->json($this->eventWidgetService->upcoming());
    }

    public function nextEclipse(): JsonResponse
    {
        return response()->json($this->eventWidgetService->nextEclipse());
    }

    public function nextMeteorShower(): JsonResponse
    {
        return response()->json($this->eventWidgetService->nextMeteorShower());
    }
}

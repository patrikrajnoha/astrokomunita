<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Widgets\NasaIotdWidgetService;
use Illuminate\Http\JsonResponse;

class NasaIotdController extends Controller
{
    public function __construct(
        private readonly NasaIotdWidgetService $nasaIotdWidgetService,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->nasaIotdWidgetService->payload());
    }
}

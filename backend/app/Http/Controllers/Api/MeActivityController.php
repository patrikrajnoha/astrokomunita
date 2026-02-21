<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeActivityController extends Controller
{
    public function __construct(
        private readonly UserActivityService $activityService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            $this->activityService->getActivity($request->user())
        );
    }
}

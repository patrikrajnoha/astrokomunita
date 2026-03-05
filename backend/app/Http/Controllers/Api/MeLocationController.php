<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Location\IpLocationService;
use App\Services\Location\UserLocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeLocationController extends Controller
{
    public function auto(Request $request, IpLocationService $service): JsonResponse
    {
        $payload = $service->lookup($request->ip());

        if ($payload === null) {
            return ApiResponse::error('Automatic location lookup is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function update(Request $request, UserLocationService $userLocationService): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location_label' => ['nullable', 'string', 'max:80'],
            'location_source' => ['nullable', 'string', Rule::in(['preset', 'gps', 'manual'])],
            'location' => ['nullable', 'string', 'max:60'], // legacy payload compatibility
        ]);

        $user = $userLocationService->update($request->user(), $validated);

        return response()->json($user);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeDataExportSummaryController extends Controller
{
    public function __invoke(Request $request, UserDataExportService $exportService): JsonResponse
    {
        return response()->json(
            $exportService->summary($request->user())
        );
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Translation\EventTranslationBackfillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventTranslationController extends Controller
{
    public function __construct(
        private readonly EventTranslationBackfillService $backfillService,
    ) {
    }

    public function backfill(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'dry_run' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacia zlyhala.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $summary = $this->backfillService->run(
            limit: (int) ($data['limit'] ?? 0),
            dryRun: (bool) ($data['dry_run'] ?? false),
            force: (bool) ($data['force'] ?? false)
        );

        $status = (int) $summary['failed'] > 0 ? 'partial_failed' : 'ok';

        return response()->json([
            'status' => $status,
            'summary' => $summary,
        ]);
    }
}


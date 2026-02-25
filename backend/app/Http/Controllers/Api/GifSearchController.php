<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GifSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GifSearchController extends Controller
{
    public function __construct(
        private readonly GifSearchService $gifs,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->gifs->search(
                (string) $validated['q'],
                (int) ($validated['limit'] ?? 20),
                (int) ($validated['offset'] ?? 0),
            );
        } catch (RuntimeException $exception) {
            $status = (int) $exception->getCode();
            $status = $status >= 400 && $status <= 599 ? $status : 503;

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'GIF_SEARCH_UNAVAILABLE',
            ], $status);
        }

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}

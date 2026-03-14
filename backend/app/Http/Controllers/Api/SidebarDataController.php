<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Widgets\SidebarWidgetBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarDataController extends Controller
{
    public function __construct(
        private readonly SidebarWidgetBundleService $sidebarWidgetBundleService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $sections = $this->extractRequestedSections($request);
        $normalizedSections = $this->sidebarWidgetBundleService->normalizeRequestedSections($sections);

        return response()->json([
            'requested_sections' => $normalizedSections,
            'data' => $this->sidebarWidgetBundleService->payloadForSections($normalizedSections),
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractRequestedSections(Request $request): array
    {
        $raw = $request->query('sections');

        if (is_string($raw)) {
            return array_values(array_filter(array_map(
                static fn (string $entry): string => trim($entry),
                explode(',', $raw),
            ), static fn (string $entry): bool => $entry !== ''));
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => trim((string) $entry),
            $raw,
        ), static fn (string $entry): bool => $entry !== ''));
    }
}

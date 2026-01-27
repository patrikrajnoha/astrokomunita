<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCandidate;
use Illuminate\Http\Request;

class EventCandidateMetaController extends Controller
{
    public function __invoke(Request $request)
    {
        $types = EventCandidate::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        $rawTypes = EventCandidate::query()
            ->whereNotNull('raw_type')
            ->where('raw_type', '!=', '')
            ->distinct()
            ->orderBy('raw_type')
            ->pluck('raw_type')
            ->values();

        $sourceNames = EventCandidate::query()
            ->whereNotNull('source_name')
            ->where('source_name', '!=', '')
            ->distinct()
            ->orderBy('source_name')
            ->pluck('source_name')
            ->values();

        $statuses = EventCandidate::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->values();

        return response()->json([
            'types'        => $types,
            'raw_types'    => $rawTypes,
            'source_names' => $sourceNames,
            'statuses'     => $statuses,
        ]);
    }
}

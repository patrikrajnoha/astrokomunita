<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SidebarSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SidebarSectionController extends Controller
{
    public function index()
    {
        $sections = SidebarSection::ordered()->get();
        
        return response()->json([
            'data' => $sections,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|integer|exists:sidebar_sections,id',
            'sections.*.sort_order' => 'required|integer|min:0',
            'sections.*.is_visible' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['sections'] as $sectionData) {
                $section = SidebarSection::findOrFail($sectionData['id']);
                $section->update([
                    'sort_order' => $sectionData['sort_order'],
                    'is_visible' => $sectionData['is_visible'],
                ]);
            }

            DB::commit();

            $updatedSections = SidebarSection::ordered()->get();

            return response()->json([
                'message' => 'Sidebar sections updated successfully',
                'data' => $updatedSections,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update sidebar sections',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

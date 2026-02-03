<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SidebarSection;

class SidebarSectionController extends Controller
{
    public function index()
    {
        $sections = SidebarSection::visible()
            ->ordered()
            ->get(['key', 'title', 'sort_order']);

        return response()->json([
            'data' => $sections,
        ]);
    }
}

<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function execute(Request $request, string $tool): JsonResponse
    {
        return response()->json(['success' => true, 'tool' => $tool]);
    }
}

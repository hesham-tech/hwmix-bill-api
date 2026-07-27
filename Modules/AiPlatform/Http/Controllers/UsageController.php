<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    public function report(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'report' => []]);
    }
}

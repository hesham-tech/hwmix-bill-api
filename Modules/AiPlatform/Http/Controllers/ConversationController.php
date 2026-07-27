<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    public function show(Request $request, string $ulid): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['ulid' => $ulid]]);
    }
}

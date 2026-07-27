<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    public function show(Request $request, string $ulid): JsonResponse
    {
        return response()->json(['success' => true, 'ulid' => $ulid]);
    }

    public function cancel(Request $request, string $ulid): JsonResponse
    {
        return response()->json(['success' => true, 'cancelled' => true]);
    }
}

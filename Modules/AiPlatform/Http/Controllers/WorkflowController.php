<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function run(Request $request, string $workflow): JsonResponse
    {
        return response()->json(['success' => true, 'workflow' => $workflow]);
    }

    public function status(Request $request, string $ulid): JsonResponse
    {
        return response()->json(['success' => true, 'ulid' => $ulid]);
    }
}

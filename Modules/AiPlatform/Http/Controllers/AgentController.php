<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function chat(Request $request, string $agent): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Agent chat endpoint']);
    }

    public function createConversation(Request $request, string $agent): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Conversation create endpoint']);
    }
}

<?php

namespace Modules\AiPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Facades\AI;

/**
 * دالة تنفيذ القدرات المباشرة للذكاء الاصطناعي مع تتبع مساري دقيق عبر Logs
 */
class CapabilityController extends Controller
{
    public function run(Request $request, string $capability): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $promptKey = $request->input('prompt_key', '');
        $variables = $request->input('variables', []);

        Log::info("[AI Milestone Trace] [Controller Enter] Capability: {$capability}, Company: {$companyId}, PromptKey: {$promptKey}");

        try {
            $builder = AI::capability($capability)
                ->forCompany($companyId)
                ->with($variables);

            if (!empty($promptKey)) {
                $builder->prompt($promptKey);
            }

            $result = $builder->run();

            Log::info("[AI Milestone Trace] [Controller Exit] TraceID: {$result->traceId}, Success: " . ($result->successful ? 'true' : 'false'));

            return response()->json([
                'success'      => $result->successful,
                'data'         => [
                    'result'       => $result->content,
                    'inputTokens'  => $result->inputTokens,
                    'outputTokens' => $result->outputTokens,
                    'totalCost'    => $result->totalCost,
                    'latencyMs'    => $result->latencyMs,
                    'traceId'      => $result->traceId,
                ],
                'error_code'   => $result->errorCode,
                'error_source' => $result->errorSource,
                'error_type'   => $result->errorType,
                'message'      => $result->successful ? 'تم التوليد بنجاح' : ($result->errorMessage ?? $result->errorCode ?? 'تعذر توليد الإجابة'),
            ]);
        } catch (\Throwable $e) {
            Log::error("[AI Milestone Trace] [Controller Exception] Error: {$e->getMessage()}");
            return response()->json([
                'success'      => false,
                'error_code'   => 'AI_EXECUTION_FAILED',
                'error_source' => 'Controller',
                'message'      => 'تعذر تشغيل الخدمة الذكية: ' . $e->getMessage(),
            ], 500);
        }
    }
}

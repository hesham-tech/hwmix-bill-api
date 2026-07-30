<?php
// محول استدعاء منصة الذكاء الاصطناعي وتطبيق البرومبت المخصص لكل مصدر مع تتبع تفاصيل التنفيذ.

namespace Modules\HwnixCash\Services\Analysis;

use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\HwnixCash\Domain\Contracts\FinancialSmsAnalyzerInterface;
use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;

class AiFinancialSmsAnalyzer implements FinancialSmsAnalyzerInterface
{
    public function __construct(
        protected ExecutionEngineInterface $executionEngine,
        protected FinancialSmsPromptResolver $promptResolver,
        protected SmsResultValidator $validator
    ) {}

    public function analyze(string $smsBody, int $companyId): NormalizedFinancialSmsDTO
    {
        $startTime = microtime(true);
        $resolvedPrompt = $this->promptResolver->resolvePrompt($smsBody);

        try {
            $request = new ExecutionRequestDTO(
                capabilityKey: 'text.generate',
                sourceType: 'direct',
                companyId: $companyId,
                options: [
                    'temperature' => 0.0,
                    'max_tokens' => 1000,
                ],
                promptVariables: [
                    'product_name' => 'Financial SMS Extraction',
                    'features' => 'JSON Extraction',
                    'raw_prompt' => $resolvedPrompt['prompt_text'],
                ]
            );

            $aiResult = $this->executionEngine->run($request);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $executionMetadata = [
                'capability' => 'text.generate',
                'company_id' => $companyId,
                'prompt_version' => $resolvedPrompt['prompt_version'],
                'schema_version' => $resolvedPrompt['schema_version'],
                'latency_ms' => $latencyMs,
                'tokens_used' => $aiResult->totalTokens ?? 0,
                'cost' => $aiResult->cost ?? 0.0,
                'trace_id' => $aiResult->traceId ?? null,
            ];

            if (!$aiResult->successful || empty($aiResult->content)) {
                Log::warning("[AiFinancialSmsAnalyzer] AI Execution failed for company {$companyId}: {$aiResult->errorMessage}");
                return $this->validator->validateAndNormalize('', $resolvedPrompt['prompt_version'], $executionMetadata);
            }

            return $this->validator->validateAndNormalize($aiResult->content, $resolvedPrompt['prompt_version'], $executionMetadata);

        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            Log::error("[AiFinancialSmsAnalyzer] Exception during AI execution: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return $this->validator->validateAndNormalize('', $resolvedPrompt['prompt_version'], [
                'error' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ]);
        }
    }
}

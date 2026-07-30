<?php
// مكون تحليل الرسائل القصيرة والمالية للمحافظ الإلكترونية كإضافة منصية تعتمد سياسة SYSTEM_ONLY بحسابات النظام فقط.

namespace Modules\AiPlatform\Engines\AnalysisEngine\Analyzers;

use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Analysis\AnalyzerInterface;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\Enums\ExecutionPolicy;
use Modules\AiPlatform\Engines\AnalysisEngine\Resolvers\FinancialSmsPromptResolver;
use Modules\AiPlatform\Engines\AnalysisEngine\Validators\FinancialSmsValidator;

class FinancialSmsAnalyzer implements AnalyzerInterface
{
    public const ANALYZER_VERSION = '1.0.0';

    public function __construct(
        protected FinancialSmsPromptResolver $promptResolver,
        protected FinancialSmsValidator $validator
    ) {}

    public function getKey(): string
    {
        return 'financial_sms';
    }

    public function getVersion(): string
    {
        return self::ANALYZER_VERSION;
    }

    /**
     * سياسة تنفيذ تحليل الرسائل المالية تعتمد حصرياً على حسابات النظام (SYSTEM_ONLY).
     */
    public function getExecutionPolicy(): ExecutionPolicy
    {
        return ExecutionPolicy::SYSTEM_ONLY;
    }

    public function getSupportedTypes(): array
    {
        return ['financial_sms', 'mobile_wallet', 'cash_notification'];
    }

    public function getSupportedProviders(): array
    {
        return ['vodafone_cash', 'orange_cash', 'etisalat_cash', 'we_cash', 'cib', 'instapay', 'general'];
    }

    public function analyze(AnalysisRequestDTO $request, ExecutionEngineInterface $executionEngine): array
    {
        $startTime = microtime(true);
        $resolvedPrompt = $this->promptResolver->resolve($request->content, $request->providerKey);

        // تفويض استدعاء الذكاء الاصطناعي مع التوجيه المعماري لسياسة SYSTEM_ONLY
        $executionRequest = new ExecutionRequestDTO(
            capabilityKey: 'text.generate',
            sourceType: 'direct',
            companyId: $request->companyId, // يُمرر فقط للحفظ والتتبع وعزل البيانات (Multi-Tenancy)
            options: array_merge([
                'temperature' => 0.0,
                'max_tokens' => 1000,
                'execution_policy' => $this->getExecutionPolicy()->value, // SYSTEM_ONLY
            ], $request->options),
            promptVariables: [
                'product_name' => 'Financial SMS Analyzer Plugin',
                'features' => 'JSON Extraction',
                'raw_prompt' => $resolvedPrompt['prompt_text'],
            ]
        );

        $aiResult = $executionEngine->run($executionRequest);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $executionMetadata = [
            'capability' => 'text.generate',
            'company_id' => $request->companyId,
            'execution_policy' => $this->getExecutionPolicy()->value,
            'prompt_version' => $resolvedPrompt['prompt_version'],
            'schema_version' => $resolvedPrompt['schema_version'],
            'analyzer_version' => self::ANALYZER_VERSION,
            'latency_ms' => $latencyMs,
            'tokens_used' => $aiResult->totalTokens ?? 0,
            'cost' => $aiResult->cost ?? 0.0,
            'trace_id' => $aiResult->traceId ?? null,
            'ai_model' => 'gemini-flash',
        ];

        $rawContent = $aiResult->successful ? ($aiResult->content ?? '') : '';

        if (!$aiResult->successful) {
            Log::warning("[FinancialSmsAnalyzer Plugin] LLM Execution failed via ExecutionEngine: {$aiResult->errorMessage}");
        }

        $normalized = $this->validator->validateAndNormalize($rawContent, $resolvedPrompt['prompt_version']);
        $normalized['analyzer_version'] = self::ANALYZER_VERSION;
        $normalized['execution_metadata'] = $executionMetadata;

        return $normalized;
    }
}

<?php
// محرك إدارة وتنسيق دورة حياة التحليل الهيكلي المنصي وتطبيق معمارية المكونات القابلة للإضافة والتتبع الموحد التراكمي.

namespace Modules\AiPlatform\Engines;

use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\AiPlatform\DTOs\AnalysisResultDTO;
use Modules\AiPlatform\Engines\AnalysisEngine\AnalyzerRegistry;
use Modules\AiPlatform\Events\AnalysisCompletedEvent;
use Modules\AiPlatform\Models\AiAnalysisResult;

class AnalysisEngine implements AnalysisEngineInterface
{
    public function __construct(
        protected AnalyzerRegistry $analyzerRegistry,
        protected ExecutionEngineInterface $executionEngine
    ) {}

    public function analyze(AnalysisRequestDTO $request): AnalysisResultDTO
    {
        Log::info("[AnalysisEngine Orchestrator] [Correlation: {$request->correlationId}] Starting analysis '{$request->analysisType}' for Company ID {$request->companyId}");

        // 1. حساب البصمة الرقمية للرسالة (Fingerprint Hashing for Deduplication Caching)
        $fingerprint = hash('sha256', $request->companyId . '|' . $request->providerKey . '|' . trim($request->content));

        // 2. فحص التخزين المؤقت الذكي (Analysis Fingerprint Caching)
        $cachedRecord = AiAnalysisResult::where('company_id', $request->companyId)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'valid')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('id')
            ->first();

        if ($cachedRecord) {
            Log::info("[AnalysisEngine Orchestrator] [Correlation: {$request->correlationId}] Cache Hit! Reusing analysis record ID {$cachedRecord->id} (0ms, $0 cost)");

            $cachedDto = new AnalysisResultDTO(
                resultId: $cachedRecord->id,
                correlationId: $request->correlationId,
                analysisType: $cachedRecord->analysis_type,
                messageType: $cachedRecord->message_type,
                isValid: true,
                isTransaction: !empty($cachedRecord->normalized_json['is_transaction']),
                amount: $cachedRecord->normalized_json['amount'] ?? null,
                currency: $cachedRecord->normalized_json['currency'] ?? 'EGP',
                targetPhone: $cachedRecord->normalized_json['target_phone'] ?? null,
                targetName: $cachedRecord->normalized_json['target_name'] ?? null,
                transactionId: $cachedRecord->normalized_json['transaction_id'] ?? null,
                datetime: $cachedRecord->normalized_json['datetime'] ?? null,
                balanceFound: !empty($cachedRecord->normalized_json['balance_found']),
                availableBalance: $cachedRecord->normalized_json['available_balance'] ?? null,
                confidenceScore: $cachedRecord->confidence_score,
                schemaVersion: $cachedRecord->schema_version,
                promptVersion: $cachedRecord->prompt_version,
                parserVersion: $cachedRecord->parser_version,
                validationErrors: [],
                executionMetadata: array_merge($cachedRecord->execution_metadata ?? [], ['cached' => true]),
                normalizedJson: $cachedRecord->normalized_json ?? [],
                rawResponse: $cachedRecord->raw_response
            );

            event(new AnalysisCompletedEvent($cachedDto));
            return $cachedDto;
        }

        // 3. استخراج الـ Analyzer Plugin المخصص من السجل (Smart Capability Matching)
        $analyzer = $this->analyzerRegistry->get($request->analysisType);

        if (!$analyzer) {
            Log::error("[AnalysisEngine Orchestrator] No registered Analyzer plugin found for key '{$request->analysisType}'");
            return $this->buildUnregisteredFailureResult($request);
        }

        // 4. تفويض المعالجة بالكامل للـ Plugin المخصص
        $normalized = $analyzer->analyze($request, $this->executionEngine);

        $status = 'completed';
        if ($normalized['message_type'] === 'unknown' || !empty($normalized['validation_errors'])) {
            $status = 'needs_review';
        }

        $executionMetadata = $normalized['execution_metadata'] ?? [];
        $analyzerVersion = $normalized['analyzer_version'] ?? $analyzer->getVersion();

        // 5. حفظ وتثبيت نتيجة التحليل المنظم كأصل دائم بجدول المنصة الموحد ai_analysis_results (Immutable Record)
        $analysisRecord = AiAnalysisResult::create([
            'company_id' => $request->companyId,
            'correlation_id' => $request->correlationId,
            'source_type' => $request->sourceType,
            'source_id' => $request->sourceId,
            'analysis_type' => $request->analysisType,
            'provider' => $request->providerKey,
            'fingerprint' => $fingerprint,
            'status' => $status,
            'confidence_score' => $normalized['confidence_score'] ?? 0,
            'schema_version' => $normalized['schema_version'] ?? '1.0',
            'prompt_version' => $normalized['prompt_version'] ?? '1.0',
            'parser_version' => $analyzerVersion,
            'ai_model' => $executionMetadata['ai_model'] ?? 'gemini-flash',
            'normalized_json' => [
                'is_transaction' => $normalized['is_transaction'] ?? false,
                'amount' => $normalized['amount'] ?? null,
                'currency' => $normalized['currency'] ?? 'EGP',
                'target_phone' => $normalized['target_phone'] ?? null,
                'target_name' => $normalized['target_name'] ?? null,
                'transaction_id' => $normalized['transaction_id'] ?? null,
                'datetime' => $normalized['datetime'] ?? null,
                'balance_found' => $normalized['balance_found'] ?? false,
                'available_balance' => $normalized['available_balance'] ?? null,
                'validation_errors' => $normalized['validation_errors'] ?? [],
            ],
            'raw_response' => isset($normalized['raw_ai_output']['raw_unparsed_content'])
                ? (string) $normalized['raw_ai_output']['raw_unparsed_content']
                : json_encode($normalized['raw_ai_output'] ?? [], JSON_UNESCAPED_UNICODE),
            'execution_metadata' => $executionMetadata,
        ]);

        Log::info("[AnalysisEngine Orchestrator] Persisted AiAnalysisResult ID {$analysisRecord->id} with status '{$status}' [Correlation: {$request->correlationId}]");

        // 6. بناء وإرجاع الـ DTO المعير المنصي
        $resultDto = new AnalysisResultDTO(
            resultId: $analysisRecord->id,
            correlationId: $request->correlationId,
            analysisType: $request->analysisType,
            messageType: $normalized['message_type'] ?? 'unknown',
            isValid: $normalized['is_valid'] ?? false,
            isTransaction: $normalized['is_transaction'] ?? false,
            amount: $normalized['amount'] ?? null,
            currency: $normalized['currency'] ?? 'EGP',
            targetPhone: $normalized['target_phone'] ?? null,
            targetName: $normalized['target_name'] ?? null,
            transactionId: $normalized['transaction_id'] ?? null,
            datetime: $normalized['datetime'] ?? null,
            balanceFound: $normalized['balance_found'] ?? false,
            availableBalance: $normalized['available_balance'] ?? null,
            confidenceScore: $normalized['confidence_score'] ?? 0,
            schemaVersion: $normalized['schema_version'] ?? '1.0',
            promptVersion: $normalized['prompt_version'] ?? '1.0',
            parserVersion: $analyzerVersion,
            validationErrors: $normalized['validation_errors'] ?? [],
            executionMetadata: $executionMetadata,
            normalizedJson: $analysisRecord->normalized_json ?? [],
            rawResponse: $analysisRecord->raw_response
        );

        // 7. إطلاق حدث إتمام التحليل المنصي عبر الـ Event Bus
        event(new AnalysisCompletedEvent($resultDto));

        return $resultDto;
    }

    protected function buildUnregisteredFailureResult(AnalysisRequestDTO $request): AnalysisResultDTO
    {
        return new AnalysisResultDTO(
            resultId: null,
            correlationId: $request->correlationId,
            analysisType: $request->analysisType,
            messageType: 'unknown',
            isValid: false,
            isTransaction: false,
            amount: null,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: null,
            balanceFound: false,
            availableBalance: null,
            confidenceScore: 0,
            schemaVersion: '1.0',
            promptVersion: '1.0',
            parserVersion: '1.0.0',
            validationErrors: ["مكون التحليل '{$request->analysisType}' غير مسجل في السجل المنصي AnalyzerRegistry"],
            executionMetadata: [],
            normalizedJson: [],
            rawResponse: null
        );
    }
}

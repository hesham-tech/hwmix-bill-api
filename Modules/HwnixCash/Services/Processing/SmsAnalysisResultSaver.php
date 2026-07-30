<?php
// كلاس حفظ وتثبيت نتائج تحليل الذكاء الاصطناعي كأصول دائمة في قاعدة البيانات.

namespace Modules\HwnixCash\Services\Processing;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;
use Modules\HwnixCash\Models\HwnixCashSmsAnalysisResult;

class SmsAnalysisResultSaver
{
    /**
     * حفظ النتيجة المعيرة للتحليل واستجابة الذكاء الاصطناعي كأصل مستقل دائم بالنظام.
     */
    public function save(int $companyId, int $messageId, NormalizedFinancialSmsDTO $dto, string $provider = 'general'): HwnixCashSmsAnalysisResult
    {
        $status = 'valid';
        if ($dto->messageType === 'unknown' || !empty($dto->validationErrors)) {
            $status = 'needs_review';
        }

        $rawResponse = isset($dto->rawAiOutput['raw_unparsed_content'])
            ? (string) $dto->rawAiOutput['raw_unparsed_content']
            : json_encode($dto->rawAiOutput, JSON_UNESCAPED_UNICODE);

        $record = HwnixCashSmsAnalysisResult::create([
            'company_id' => $companyId,
            'message_id' => $messageId,
            'provider' => $provider,
            'message_type' => $dto->messageType,
            'status' => $status,
            'confidence_score' => $dto->confidenceScore,
            'schema_version' => $dto->schemaVersion,
            'prompt_version' => $dto->promptVersion,
            'parser_version' => $dto->parserVersion,
            'ai_model' => $dto->executionMetadata['ai_model'] ?? 'gemini-flash',
            'normalized_json' => [
                'is_transaction' => $dto->isTransaction,
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'target_phone' => $dto->targetPhone,
                'target_name' => $dto->targetName,
                'transaction_id' => $dto->transactionId,
                'datetime' => $dto->datetime,
                'balance_found' => $dto->balanceFound,
                'available_balance' => $dto->availableBalance,
                'validation_errors' => $dto->validationErrors,
            ],
            'raw_response' => $rawResponse,
            'execution_metadata' => $dto->executionMetadata,
        ]);

        Log::info("[SmsAnalysisResultSaver] Persisted SMS Analysis Result ID {$record->id} for Message ID {$messageId}");

        return $record;
    }
}

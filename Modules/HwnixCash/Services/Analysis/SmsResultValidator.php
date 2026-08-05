<?php
// طبقة التحقق من صحة نواتج الذكاء الاصطناعي وحساب الثقة وتطبيع البيانات دون تخمين أو تعديل قسري.

namespace Modules\HwnixCash\Services\Analysis;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;

class SmsResultValidator
{
    /**
     * فحص وتطهير وتطبيع الاستجابة الخام وحساب نسبة الثقة البرمجية.
     */
    public function validateAndNormalize(
        string $rawContent,
        string $promptVersion = '1.0',
        array $executionMetadata = []
    ): NormalizedFinancialSmsDTO {
        $validationErrors = [];
        $confidenceScore = 100;

        $jsonArray = $this->parseJson($rawContent);

        if (!$jsonArray || !is_array($jsonArray)) {
            Log::warning("[SmsResultValidator] Invalid JSON output from AI. Content: {$rawContent}");
            return $this->buildFallbackDto($rawContent, ['json_parse_error' => 'الاستجابة ليست كائن JSON صالح'], $promptVersion, $executionMetadata);
        }

        $schemaVersion = (string) ($jsonArray['schema_version'] ?? '1.0');

        // 1. التحقق وتطبيع نوع الحركة
        $rawType = strtolower((string) ($jsonArray['transaction']['type'] ?? 'unknown'));
        $isTx = !empty($jsonArray['is_transaction']);
        $messageType = $this->determineMessageType($rawType, $isTx);

        if ($messageType === 'unknown') {
            $confidenceScore = 0;
            $validationErrors[] = 'نوع الحركة غير معروف أو غير مدعوم (unknown_message_type)';
        }

        // 2. التحقق من المبلغ المالي بشكل صارم (بدون تخمين أو تحويل قسري)
        $rawAmount = $jsonArray['transaction']['amount'] ?? null;
        $amount = null;
        if ($isTx) {
            if ($rawAmount === null) {
                $validationErrors[] = 'المبلغ المالي مفقود في معاملة مالية (missing_amount)';
                $confidenceScore -= 60;
                $isTx = false;
            } elseif (!is_numeric($rawAmount) || (float) $rawAmount <= 0) {
                $validationErrors[] = "المبلغ المالي غير صالح أو ليس رقماً: '{$rawAmount}' (invalid_numeric_amount)";
                $confidenceScore -= 60;
                $isTx = false;
            } else {
                $amount = round((float) $rawAmount, 2);
            }
        }

        // 3. التحقق وتطهير الرصيد المتاح
        $balanceFound = !empty($jsonArray['balance']['found']);
        $rawAvailable = $jsonArray['balance']['available'] ?? null;
        $availableBalance = null;

        if ($balanceFound) {
            if ($rawAvailable !== null && is_numeric($rawAvailable)) {
                $availableBalance = round((float) $rawAvailable, 2);
            } else {
                $validationErrors[] = "الرصيد المالي المتاح ليس رقماً صالحاً: '{$rawAvailable}' (invalid_numeric_balance)";
                $balanceFound = false;
            }
        }

        // 4. تقييم نسبة الثقة لنواقص المعاملات المالية
        if ($isTx) {
            if (empty($jsonArray['transaction']['transaction_id'])) {
                $confidenceScore -= 10;
            }
            if (empty($jsonArray['transaction']['datetime'])) {
                $confidenceScore -= 10;
            }
        }

        $confidenceScore = max(0, min(100, $confidenceScore));

        // 5. تطهير الحقول النصية
        $currency = !empty($jsonArray['transaction']['currency']) ? strtoupper(trim((string) $jsonArray['transaction']['currency'])) : 'EGP';
        $targetPhone = !empty($jsonArray['transaction']['phone']) ? trim((string) $jsonArray['transaction']['phone']) : null;
        $targetName = !empty($jsonArray['transaction']['name']) ? trim((string) $jsonArray['transaction']['name']) : null;
        $transactionId = !empty($jsonArray['transaction']['transaction_id']) ? trim((string) $jsonArray['transaction']['transaction_id']) : null;
        $datetime = !empty($jsonArray['transaction']['datetime']) ? trim((string) $jsonArray['transaction']['datetime']) : null;
        $fee = isset($jsonArray['transaction']['fee']) ? (float) $jsonArray['transaction']['fee'] : (isset($jsonArray['transaction']['service_fee']) ? (float) $jsonArray['transaction']['service_fee'] : 0.0);

        return new NormalizedFinancialSmsDTO(
            messageType: $messageType,
            isTransaction: $isTx && $amount !== null && $amount > 0,
            amount: $amount,
            fee: $fee,
            currency: $currency,
            targetPhone: $targetPhone,
            targetName: $targetName,
            transactionId: $transactionId,
            datetime: $datetime,
            balanceFound: $balanceFound && $availableBalance !== null,
            availableBalance: $availableBalance,
            confidenceScore: $confidenceScore,
            schemaVersion: $schemaVersion,
            promptVersion: $promptVersion,
            validationErrors: $validationErrors,
            executionMetadata: $executionMetadata,
            rawAiOutput: $jsonArray
        );
    }

    protected function parseJson(string $content): ?array
    {
        $clean = trim($content);
        $clean = preg_replace('/^```(?:json)?/i', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    protected function determineMessageType(string $rawType, bool $isTx): string
    {
        if (!$isTx) {
            return ($rawType === 'balance_inquiry') ? 'balance_inquiry' : 'promotion';
        }

        return match ($rawType) {
            'receive' => 'wallet_receive',
            'send' => 'wallet_send',
            'withdraw' => 'wallet_withdraw',
            'deposit' => 'wallet_deposit',
            'payment' => 'wallet_payment',
            'refund' => 'wallet_refund',
            'transfer' => 'wallet_send',
            'balance_inquiry' => 'balance_inquiry',
            default => 'unknown',
        };
    }

    protected function buildFallbackDto(
        string $rawContent,
        array $errors,
        string $promptVersion,
        array $executionMetadata
    ): NormalizedFinancialSmsDTO {
        return new NormalizedFinancialSmsDTO(
            messageType: 'unknown',
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
            promptVersion: $promptVersion,
            validationErrors: array_values($errors),
            executionMetadata: $executionMetadata,
            rawAiOutput: ['raw_unparsed_content' => $rawContent]
        );
    }
}

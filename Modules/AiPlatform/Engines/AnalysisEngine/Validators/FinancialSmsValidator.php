<?php
// مدقق نواتج استجابات الذكاء الاصطناعي للرسائل المالية والتحقق الصارم وحساب الثقة.

namespace Modules\AiPlatform\Engines\AnalysisEngine\Validators;

use Illuminate\Support\Facades\Log;

class FinancialSmsValidator
{
    public function validateAndNormalize(
        string $rawContent,
        string $promptVersion = '1.0'
    ): array {
        $validationErrors = [];
        $confidenceScore = 100;

        $jsonArray = $this->parseJson($rawContent);

        if (!$jsonArray || !is_array($jsonArray)) {
            Log::warning("[FinancialSmsValidator] Invalid JSON output from AI. Content: {$rawContent}");
            return $this->buildFallbackArray($rawContent, ['json_parse_error' => 'الاستجابة ليست كائن JSON صالح'], $promptVersion);
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

        // 5. تطهير الحقول النصية والمالية
        $rawFee = $jsonArray['transaction']['fee'] ?? null;
        $fee = (is_numeric($rawFee) && (float) $rawFee >= 0) ? round((float) $rawFee, 2) : 0.0;

        $currency = !empty($jsonArray['transaction']['currency']) ? strtoupper(trim((string) $jsonArray['transaction']['currency'])) : 'EGP';
        $targetPhone = !empty($jsonArray['transaction']['phone']) ? trim((string) $jsonArray['transaction']['phone']) : null;
        $targetName = !empty($jsonArray['transaction']['name']) ? trim((string) $jsonArray['transaction']['name']) : null;
        $transactionId = !empty($jsonArray['transaction']['transaction_id']) ? trim((string) $jsonArray['transaction']['transaction_id']) : null;
        $datetime = !empty($jsonArray['transaction']['datetime']) ? trim((string) $jsonArray['transaction']['datetime']) : null;

        return [
            'message_type' => $messageType,
            'is_valid' => empty($validationErrors) && $messageType !== 'unknown',
            'is_transaction' => $isTx && $amount !== null && $amount > 0,
            'amount' => $amount,
            'fee' => $fee,
            'currency' => $currency,
            'target_phone' => $targetPhone,
            'target_name' => $targetName,
            'transaction_id' => $transactionId,
            'datetime' => $datetime,
            'balance_found' => $balanceFound && $availableBalance !== null,
            'available_balance' => $availableBalance,
            'confidence_score' => $confidenceScore,
            'schema_version' => $schemaVersion,
            'prompt_version' => $promptVersion,
            'validation_errors' => $validationErrors,
            'raw_ai_output' => $jsonArray,
        ];
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

    protected function buildFallbackArray(string $rawContent, array $errors, string $promptVersion): array
    {
        return [
            'message_type' => 'unknown',
            'is_valid' => false,
            'is_transaction' => false,
            'amount' => null,
            'currency' => 'EGP',
            'target_phone' => null,
            'target_name' => null,
            'transaction_id' => null,
            'datetime' => null,
            'balance_found' => false,
            'available_balance' => null,
            'confidence_score' => 0,
            'schema_version' => '1.0',
            'prompt_version' => $promptVersion,
            'validation_errors' => array_values($errors),
            'raw_ai_output' => ['raw_unparsed_content' => $rawContent],
        ];
    }
}

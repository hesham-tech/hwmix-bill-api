<?php
// كلاس نقل نواتج التحليل الهيكلي المعيرة الصادرة من محرك التحليل والمسجلة في أصول المنظومة مع معرف التتبع الموحد.

namespace Modules\AiPlatform\DTOs;

final class AnalysisResultDTO
{
    public function __construct(
        public readonly ?int $resultId,              // معرف سجل ai_analysis_results المكتوب بجدول المنصة
        public readonly string $correlationId,        // معرف التتبع السلسلي الموحد (Correlation ID)
        public readonly string $analysisType,        // financial_sms, bank_statement, invoice_ocr, email, whatsapp
        public readonly string $messageType,         // wallet_receive, wallet_send, wallet_withdraw, wallet_deposit, wallet_payment, wallet_refund, balance_inquiry, promotion, unknown
        public readonly bool $isValid,
        public readonly bool $isTransaction,
        public readonly ?float $amount,
        public readonly ?float $fee = 0.0,
        public readonly ?string $currency,
        public readonly ?string $targetPhone,
        public readonly ?string $targetName,
        public readonly ?string $transactionId,
        public readonly ?string $datetime,
        public readonly bool $balanceFound,
        public readonly ?float $availableBalance,
        public readonly int $confidenceScore = 100,  // نسبة الثقة المحسوبة برمجياً (0 - 100%)
        public readonly string $schemaVersion = '1.0',
        public readonly string $promptVersion = '1.0',
        public readonly string $parserVersion = '1.0.0',
        public readonly array $validationErrors = [],
        public readonly array $executionMetadata = [],
        public readonly array $normalizedJson = [],
        public readonly ?string $rawResponse = null
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

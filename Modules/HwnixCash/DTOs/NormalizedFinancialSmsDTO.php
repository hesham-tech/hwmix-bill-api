<?php
// كلاس نقل البيانات المعيرة لنواتج تحليل الرسائل المالية مع دعم اصدار المخطط ونسبة الثقة وإصدار الـ Parser وسجلات التدقيق.

namespace Modules\HwnixCash\DTOs;

final class NormalizedFinancialSmsDTO
{
    public function __construct(
        public readonly string $messageType,        // wallet_receive, wallet_send, wallet_withdraw, wallet_deposit, wallet_payment, wallet_refund, balance_inquiry, promotion, unknown
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
        public readonly int $confidenceScore = 100, // نسبة الثقة المحسوبة برمجياً (0 - 100%)
        public readonly string $schemaVersion = '1.0',
        public readonly string $promptVersion = '1.0',
        public readonly string $parserVersion = '1.0.0', // إصدار منطق معالجة الـ Parser بالنظام
        public readonly array $validationErrors = [],
        public readonly array $executionMetadata = [],
        public readonly array $rawAiOutput = []
    ) {}
}

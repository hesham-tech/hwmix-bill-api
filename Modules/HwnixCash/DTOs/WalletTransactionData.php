<?php
// كائن نقل بيانات معاملات المحافظ الإلكترونية لكاش هونكس.

namespace Modules\HwnixCash\DTOs;

class WalletTransactionData
{
    public function __construct(
        public int $lineId,
        public string $operationType,
        public string $provider,
        public string $status,
        public string $source,
        public float $amount,
        public float $fee,
        public ?float $balanceAfter,
        public string $currency,
        public ?string $operationNumber,
        public ?string $operationAt,
        public ?string $targetPhone,
        public ?string $targetName,
        public ?string $billNumber,
        public string $rawSms,
        public ?array $metadata = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lineId: $data['line_id'],
            operationType: $data['operation_type'],
            provider: $data['provider'] ?? 'other',
            status: $data['status'] ?? 'success',
            source: $data['source'] ?? 'sms',
            amount: (float) ($data['amount'] ?? 0.00),
            fee: (float) ($data['fee'] ?? 0.00),
            balanceAfter: isset($data['balance_after']) ? (float) $data['balance_after'] : null,
            currency: $data['currency'] ?? 'EGP',
            operationNumber: $data['operation_number'] ?? null,
            operationAt: $data['operation_at'] ?? null,
            targetPhone: $data['target_phone'] ?? null,
            targetName: $data['target_name'] ?? null,
            billNumber: $data['bill_number'] ?? null,
            rawSms: $data['raw_sms'],
            metadata: $data['metadata'] ?? null
        );
    }
}

<?php
// كيان دومين يعبر عن معاملة المحفظة الإلكترونية في كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Domain\Enums\WalletTransactionSource;
use Modules\HwnixCash\Domain\Enums\WalletTransactionStatus;

class WalletTransactionEntity
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $createdBy,
        public int $lineId,
        public WalletOperationType $operationType,
        public WalletProvider $provider,
        public WalletTransactionStatus $status,
        public WalletTransactionSource $source,
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
        public ?array $metadata
    ) {}
}

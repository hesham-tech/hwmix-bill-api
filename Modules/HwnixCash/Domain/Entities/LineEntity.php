<?php
// كيان دومين يعبر عن خط الاتصال والمحفظة بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\LineStatus;

class LineEntity
{
    public function __construct(
        public ?int $id,
        public string $deviceAndroidId,
        public int $companyId,
        public int $createdBy,
        public int $slotIndex,
        public ?string $subscriptionId,
        public ?string $carrier,
        public string $phoneNumber,
        public float $balance,
        public float $actualBalance,
        public int $dailyLimit,
        public ?float $dailyWithdrawLimit,
        public ?float $dailyDepositLimit,
        public ?float $monthlyWithdrawLimit,
        public ?float $monthlyDepositLimit,
        public LineStatus $status,
        public ?string $note
    ) {}
}

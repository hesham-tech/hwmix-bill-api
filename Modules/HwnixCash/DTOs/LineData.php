<?php
// كائن نقل بيانات خطوط الاتصال والمحافظ الإلكترونية لموديول كاش هونكس.

namespace Modules\HwnixCash\DTOs;

class LineData
{
    public function __construct(
        public int $slotIndex,
        public ?string $subscriptionId,
        public ?string $carrier,
        public ?string $phoneNumber = null,
        public ?string $networkType = null,
        public ?int $signalStrength = null,
        public ?float $dailyWithdrawLimit = null,
        public ?float $dailyDepositLimit = null,
        public ?float $monthlyWithdrawLimit = null,
        public ?float $monthlyDepositLimit = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slotIndex: $data['slot_index'] ?? 0,
            subscriptionId: $data['subscription_id'] ?? null,
            carrier: $data['carrier'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            networkType: $data['network_type'] ?? null,
            signalStrength: $data['signal_strength'] ?? null,
            dailyWithdrawLimit: isset($data['daily_withdraw_limit']) ? (float) $data['daily_withdraw_limit'] : null,
            dailyDepositLimit: isset($data['daily_deposit_limit']) ? (float) $data['daily_deposit_limit'] : null,
            monthlyWithdrawLimit: isset($data['monthly_withdraw_limit']) ? (float) $data['monthly_withdraw_limit'] : null,
            monthlyDepositLimit: isset($data['monthly_deposit_limit']) ? (float) $data['monthly_deposit_limit'] : null
        );
    }
}

<?php
// كائن الدومين (Entity) الذي يمثل شريحة الاتصال (SIM Card) النشطة.

namespace Modules\SmsGateway\Domain\Entities;

use Modules\SmsGateway\Domain\Enums\LineStatus;

class SIMCard
{
    public function __construct(
        public ?int $id,
        public int $deviceId,
        public int $companyId,
        public ?int $createdBy,
        public int $slotIndex,
        public string $subscriptionId,
        public string $carrier,
        public ?string $mcc,
        public ?string $mnc,
        public ?string $phoneNumber,
        public ?string $networkType,
        public ?int $signalStrength,
        public LineStatus $status,
        public ?\DateTime $createdAt = null,
        public ?\DateTime $updatedAt = null
    ) {}
}

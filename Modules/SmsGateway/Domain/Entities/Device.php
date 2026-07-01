<?php
// كائن الدومين (Entity) الذي يمثل جهاز الأندرويد المسجل في النظام.

namespace Modules\SmsGateway\Domain\Entities;

use Modules\SmsGateway\Domain\Enums\DeviceStatus;

class Device
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public ?int $createdBy,
        public string $androidId,
        public string $uuid,
        public string $deviceName,
        public string $brand,
        public string $model,
        public string $androidVersion,
        public string $appVersion,
        public array $capabilities,
        public DeviceStatus $status,
        public ?\DateTime $lastSeenAt = null,
        public ?\DateTime $createdAt = null,
        public ?\DateTime $updatedAt = null
    ) {}

    /**
     * التحقق مما إذا كان الجهاز يدعم ميزة معينة.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities);
    }
}

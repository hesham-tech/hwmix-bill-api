<?php
// كيان دومين يعبر عن هاتف بوابة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\DeviceStatus;

class Device
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $createdBy,
        public string $androidId,
        public ?string $uuid,
        public string $deviceName,
        public ?string $brand,
        public ?string $model,
        public ?string $androidVersion,
        public ?string $appVersion,
        public ?string $fcmToken,
        public ?array $capabilities,
        public DeviceStatus $status,
        public ?string $lastSeenAt
    ) {}
}

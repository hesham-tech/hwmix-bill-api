<?php
// كائن الدومين (Entity) الذي يمثل أمر تشغيل موجه لجهاز الأندرويد.

namespace Modules\SmsGateway\Domain\Entities;

use Modules\SmsGateway\Domain\Enums\CommandStatus;

class DeviceCommand
{
    public function __construct(
        public ?int $id,
        public int $deviceId,
        public string $commandType, // SEND_SMS, REFRESH_DEVICE, UPDATE_CONFIG, etc.
        public array $payload,
        public CommandStatus $status,
        public ?array $responsePayload = null,
        public ?string $idempotencyKey = null,
        public ?\DateTime $executedAt = null,
        public ?\DateTime $createdAt = null,
        public ?\DateTime $updatedAt = null
    ) {}
}

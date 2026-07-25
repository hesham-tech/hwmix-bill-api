<?php
// كيان دومين يعبر عن الأمر التشغيلي للهاتف.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\CommandStatus;

class DeviceCommand
{
    public function __construct(
        public ?int $id,
        public int $smsDeviceId,
        public string $commandType,
        public ?array $payload,
        public CommandStatus $status,
        public ?array $responsePayload,
        public ?string $idempotencyKey,
        public ?string $executedAt
    ) {}
}

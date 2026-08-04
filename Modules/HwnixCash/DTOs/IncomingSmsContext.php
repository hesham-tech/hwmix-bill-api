<?php
// كلاس نقل بيانات سياق الرسالة القادمة النقي والمجرد من تفاصيل النظام.

namespace Modules\HwnixCash\DTOs;

final class IncomingSmsContext
{
    public function __construct(
        public readonly string $body,
        public readonly string $sender,
        public readonly mixed $rawMessage = null,
        public readonly ?string $receivedAt = null,
        public readonly ?string $providerKeyHint = null,
        public readonly ?int $simSlot = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?int $deviceId = null,
        public readonly array $extraContext = []
    ) {}
}

<?php
// كائن نقل بيانات الرسائل الواردة لكاش هونكس.

namespace Modules\HwnixCash\DTOs;

class IncomingSmsData
{
    public function __construct(
        public int $deviceId,
        public ?string $subscriptionId,
        public string $phoneNumber,
        public string $messageBody,
        public string $messageRef,
        public ?string $sentAt = null,
        public ?string $contactName = null
    ) {}

    public static function fromArray(array $data, ?int $fallbackDeviceId = null): self
    {
        return new self(
            deviceId: (int) ($data['device_id'] ?? $fallbackDeviceId ?? 0),
            subscriptionId: isset($data['subscription_id']) ? (string) $data['subscription_id'] : null,
            phoneNumber: (string) ($data['phone_number'] ?? ''),
            messageBody: (string) ($data['message_body'] ?? ''),
            messageRef: (string) ($data['message_ref'] ?? ''),
            sentAt: $data['sent_at'] ?? null,
            contactName: isset($data['contact_name']) ? (string) $data['contact_name'] : null
        );
    }
}

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
        public ?string $sentAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            deviceId: $data['device_id'],
            subscriptionId: $data['subscription_id'] ?? null,
            phoneNumber: $data['phone_number'],
            messageBody: $data['message_body'],
            messageRef: $data['message_ref'],
            sentAt: $data['sent_at'] ?? null
        );
    }
}

<?php
// كائن نقل بيانات الرسائل الصادرة لكاش هونكس.

namespace Modules\HwnixCash\DTOs;

class OutgoingSmsData
{
    public function __construct(
        public int $smsLineId,
        public string $phoneNumber,
        public string $messageBody
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            smsLineId: $data['sms_line_id'],
            phoneNumber: $data['phone_number'],
            messageBody: $data['message_body']
        );
    }
}

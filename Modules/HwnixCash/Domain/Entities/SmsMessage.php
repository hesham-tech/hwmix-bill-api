<?php
// كيان دومين يعبر عن الرسالة القصيرة في كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\SmsMessageStatus;

class SmsMessage
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $createdBy,
        public ?int $smsDeviceId,
        public ?int $smsLineId,
        public string $phoneNumber,
        public string $messageBody,
        public string $direction,
        public SmsMessageStatus $status,
        public ?string $messageRef,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $sentAt
    ) {}
}

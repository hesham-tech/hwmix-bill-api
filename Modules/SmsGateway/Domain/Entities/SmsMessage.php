<?php
// كائن الدومين (Entity) الذي يمثل رسالة SMS الصادرة أو الواردة.

namespace Modules\SmsGateway\Domain\Entities;

use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;

class SmsMessage
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public ?int $createdBy,
        public int $deviceId,
        public ?int $lineId,
        public string $phoneNumber,
        public string $messageBody,
        public string $direction, // "incoming" or "outgoing"
        public SmsMessageStatus $status,
        public ?string $failureReason = null,
        public int $retryCount = 0,
        public ?string $messageRef = null, // المعرف المحلي المخزن على هاتف أندرويد
        public ?\DateTime $sentAt = null,
        public ?\DateTime $deliveredAt = null,
        public ?\DateTime $createdAt = null,
        public ?\DateTime $updatedAt = null
    ) {}

    /**
     * التحقق مما إذا كانت الرسالة واردة.
     */
    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    /**
     * التحقق مما إذا كانت الرسالة صادرة.
     */
    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }
}

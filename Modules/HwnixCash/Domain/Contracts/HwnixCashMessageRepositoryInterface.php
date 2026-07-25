<?php
// واجهة مستودع بيانات رسائل كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Contracts;

use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\DTOs\OutgoingSmsData;

interface HwnixCashMessageRepositoryInterface
{
    public function findById(int $id): ?SmsMessage;

    public function createIncoming(IncomingSmsData $dto, int $companyId, int $userId): SmsMessage;

    public function createOutgoing(OutgoingSmsData $dto, int $companyId, int $userId): SmsMessage;

    public function updateStatus(int $messageId, string $status, ?string $errorCode = null, ?string $errorMessage = null): bool;

    public function isDuplicateIncoming(int $deviceId, string $messageRef): bool;
}

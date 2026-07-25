<?php
// خدمة إرسال وجدولة الرسائل القصيرة التابعة لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Services;

use Modules\HwnixCash\Actions\DispatchOutgoingSmsAction;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\DTOs\OutgoingSmsData;

class HwnixCashDispatcherService
{
    public function __construct(
        protected DispatchOutgoingSmsAction $dispatchAction
    ) {}

    public function sendSms(int $lineId, string $phoneNumber, string $messageBody, int $companyId, int $userId): SmsMessage
    {
        $dto = new OutgoingSmsData(
            smsLineId: $lineId,
            phoneNumber: $phoneNumber,
            messageBody: $messageBody
        );

        return $this->dispatchAction->execute($dto, $companyId, $userId);
    }
}

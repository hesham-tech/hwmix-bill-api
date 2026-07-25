<?php
// خدمة الواجهة الرئيسية الموحدة لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Services;

use Modules\HwnixCash\Actions\DispatchOutgoingSmsAction;
use Modules\HwnixCash\Actions\ProcessIncomingSmsAction;
use Modules\HwnixCash\Actions\RegisterDeviceAction;
use Modules\HwnixCash\Domain\Entities\Device;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\DTOs\DeviceData;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\DTOs\OutgoingSmsData;

class HwnixCashService
{
    public function __construct(
        protected RegisterDeviceAction $registerDeviceAction,
        protected ProcessIncomingSmsAction $processIncomingSmsAction,
        protected DispatchOutgoingSmsAction $dispatchOutgoingSmsAction
    ) {}

    public function registerDevice(DeviceData $dto, int $companyId, int $userId): Device
    {
        return $this->registerDeviceAction->execute($dto, $companyId, $userId);
    }

    public function processIncomingSms(IncomingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        return $this->processIncomingSmsAction->execute($dto, $companyId, $userId);
    }

    public function dispatchOutgoingSms(OutgoingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        return $this->dispatchOutgoingSmsAction->execute($dto, $companyId, $userId);
    }
}

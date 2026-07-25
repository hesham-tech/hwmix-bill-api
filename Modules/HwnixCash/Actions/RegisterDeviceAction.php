<?php
// إجراء تسجيل وتحديث أجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\Domain\Contracts\HwnixCashDeviceRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\Device;
use Modules\HwnixCash\DTOs\DeviceData;

class RegisterDeviceAction
{
    public function __construct(
        protected HwnixCashDeviceRepositoryInterface $deviceRepo
    ) {}

    public function execute(DeviceData $dto, int $companyId, int $userId): Device
    {
        return $this->deviceRepo->createOrUpdate($dto, $companyId, $userId);
    }
}

<?php
// واجهة مستودع بيانات أجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Contracts;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Entities\Device;
use Modules\HwnixCash\DTOs\DeviceData;

interface HwnixCashDeviceRepositoryInterface
{
    public function findById(int $id): ?Device;

    public function findByAndroidId(string $androidId): ?Device;

    public function createOrUpdate(DeviceData $dto, int $companyId, int $userId): Device;

    public function getCompanyDevices(int $companyId): Collection;

    public function updateLastSeen(int $deviceId): void;

    public function delete(int $deviceId): bool;
}

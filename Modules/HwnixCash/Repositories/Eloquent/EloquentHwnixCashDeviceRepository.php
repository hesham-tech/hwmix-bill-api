<?php
// المستودع الفعلي لإدارة أجهزة كاش هونكس باستعمال Eloquent.

namespace Modules\HwnixCash\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Contracts\HwnixCashDeviceRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\Device;
use Modules\HwnixCash\Domain\Enums\DeviceStatus;
use Modules\HwnixCash\DTOs\DeviceData;
use Modules\HwnixCash\Models\HwnixCashDevice;

class EloquentHwnixCashDeviceRepository implements HwnixCashDeviceRepositoryInterface
{
    public function findById(int $id): ?Device
    {
        $model = HwnixCashDevice::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findByAndroidId(string $androidId): ?Device
    {
        $model = HwnixCashDevice::where('android_id', $androidId)->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function createOrUpdate(DeviceData $dto, int $companyId, int $userId): Device
    {
        // 1. البحث بدون فلاتر الشركة لمعالجة نقل/إعادة استخدام الأجهزة المستعملة بين الحسابات
        $device = HwnixCashDevice::withoutGlobalScopes()
            ->withTrashed()
            ->where('android_id', $dto->androidId)
            ->first();

        $attributes = [
            'company_id' => $companyId,
            'created_by' => $userId,
            'uuid' => $dto->uuid,
            'device_name' => $dto->deviceName,
            'brand' => $dto->brand,
            'model' => $dto->model,
            'android_version' => $dto->androidVersion,
            'app_version' => $dto->appVersion,
            'fcm_token' => $dto->fcmToken,
            'capabilities' => $dto->capabilities,
            'status' => 'active',
            'last_seen_at' => now(),
        ];

        if ($device) {
            if ($device->trashed()) {
                $device->restore();
            }
            $device->update($attributes);
        } else {
            $attributes['android_id'] = $dto->androidId;
            $device = HwnixCashDevice::create($attributes);
        }

        $device->settings()->firstOrCreate([], [
            'heartbeat_interval_seconds' => 60,
            'max_retry_attempts' => 3,
            'is_active' => true,
            'version' => 1,
        ]);

        return $this->toEntity($device);
    }

    public function getCompanyDevices(int $companyId): Collection
    {
        return HwnixCashDevice::where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function updateLastSeen(int $deviceId): void
    {
        HwnixCashDevice::where('id', $deviceId)->update(['last_seen_at' => now()]);
    }

    public function delete(int $deviceId): bool
    {
        return (bool) HwnixCashDevice::where('id', $deviceId)->delete();
    }

    protected function toEntity(HwnixCashDevice $model): Device
    {
        return new Device(
            id: $model->id,
            companyId: $model->company_id,
            createdBy: $model->created_by,
            androidId: $model->android_id,
            uuid: $model->uuid,
            deviceName: $model->device_name,
            brand: $model->brand,
            model: $model->model,
            androidVersion: $model->android_version,
            appVersion: $model->app_version,
            fcmToken: $model->fcm_token,
            capabilities: $model->capabilities,
            status: DeviceStatus::from($model->status),
            lastSeenAt: $model->last_seen_at?->toIso8601String()
        );
    }
}

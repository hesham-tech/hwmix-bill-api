<?php
// تنفيذ مستودع بيانات الأجهزة باستخدام Eloquent ORM.

namespace Modules\SmsGateway\Repositories\Eloquent;

use Modules\SmsGateway\Domain\Contracts\SmsDeviceRepositoryInterface;
use Modules\SmsGateway\Domain\Entities\Device;
use Modules\SmsGateway\Domain\Enums\DeviceStatus;
use Modules\SmsGateway\Models\SmsDevice;

class EloquentSmsDeviceRepository implements SmsDeviceRepositoryInterface
{
    /**
     * تحويل Eloquent Model إلى Domain Entity.
     */
    private function mapToEntity(SmsDevice $model): Device
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
            capabilities: $model->capabilities ?? [],
            status: DeviceStatus::from($model->status),
            lastSeenAt: $model->last_seen_at,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at
        );
    }

    /**
     * البحث عن جهاز بواسطة معرفه الرقمي.
     */
    public function findById(int $id): ?Device
    {
        $model = SmsDevice::find($id);
        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * البحث عن جهاز بواسطة معرف الـ UUID.
     */
    public function findByUuid(string $uuid): ?Device
    {
        $model = SmsDevice::where('uuid', $uuid)->first();
        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * البحث عن جهاز بواسطة معرف الـ Android ID.
     */
    public function findByAndroidId(string $androidId): ?Device
    {
        $model = SmsDevice::where('android_id', $androidId)->first();
        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * حفظ أو تحديث بيانات الجهاز.
     */
    public function save(Device $device): Device
    {
        $data = [
            'company_id' => $device->companyId,
            'created_by' => $device->createdBy,
            'android_id' => $device->androidId,
            'uuid' => $device->uuid,
            'device_name' => $device->deviceName,
            'brand' => $device->brand,
            'model' => $device->model,
            'android_version' => $device->androidVersion,
            'app_version' => $device->appVersion,
            'capabilities' => $device->capabilities,
            'status' => $device->status->value,
            'last_seen_at' => $device->lastSeenAt,
        ];

        if ($device->id) {
            $model = SmsDevice::findOrFail($device->id);
            $model->update($data);
        } else {
            $model = SmsDevice::create($data);
        }

        return $this->mapToEntity($model);
    }

    /**
     * حذف جهاز (Soft Delete).
     */
    public function delete(int $id): bool
    {
        $model = SmsDevice::find($id);
        return $model ? $model->delete() : false;
    }

    /**
     * الحصول على جميع الأجهزة التابعة لشركة محددة.
     */
    public function getCompanyDevices(int $companyId): array
    {
        $models = SmsDevice::where('company_id', $companyId)->get();
        return $models->map(fn($model) => $this->mapToEntity($model))->toArray();
    }
}

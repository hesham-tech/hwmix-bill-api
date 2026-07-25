<?php
// إجراء تسجيل نبضة القلب الحيوية وقياسات الأجهزة.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\DTOs\HeartbeatData;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashDeviceHeartbeat;

class RecordHeartbeatAction
{
    public function execute(HeartbeatData $dto): void
    {
        $device = HwnixCashDevice::find($dto->deviceId);
        if (!$device) {
            return;
        }

        $device->update(['last_seen_at' => now()]);

        HwnixCashDeviceHeartbeat::create([
            'sms_device_id' => $device->id,
            'network_type' => $dto->networkType,
            'battery_level' => $dto->batteryLevel,
            'is_internet_available' => $dto->isInternetAvailable,
            'free_memory_bytes' => $dto->freeMemoryBytes,
            'free_storage_bytes' => $dto->freeStorageBytes,
            'app_version' => $dto->appVersion,
            'configuration_version' => $dto->configurationVersion,
        ]);
    }
}

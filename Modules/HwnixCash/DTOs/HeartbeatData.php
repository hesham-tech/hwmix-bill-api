<?php
// كائن نقل بيانات نبضات التشغيل للهاتف.

namespace Modules\HwnixCash\DTOs;

class HeartbeatData
{
    public function __construct(
        public int $deviceId,
        public ?string $networkType = null,
        public ?int $batteryLevel = null,
        public bool $isInternetAvailable = true,
        public ?int $freeMemoryBytes = null,
        public ?int $freeStorageBytes = null,
        public ?string $appVersion = null,
        public ?int $configurationVersion = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            deviceId: $data['device_id'],
            networkType: $data['network_type'] ?? null,
            batteryLevel: $data['battery_level'] ?? null,
            isInternetAvailable: $data['is_internet_available'] ?? true,
            freeMemoryBytes: $data['free_memory_bytes'] ?? null,
            freeStorageBytes: $data['free_storage_bytes'] ?? null,
            appVersion: $data['app_version'] ?? null,
            configurationVersion: $data['configuration_version'] ?? null
        );
    }
}

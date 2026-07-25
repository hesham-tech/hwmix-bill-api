<?php
// كائن نقل بيانات الأجهزة لموديول كاش هونكس.

namespace Modules\HwnixCash\DTOs;

class DeviceData
{
    public function __construct(
        public string $androidId,
        public ?string $uuid = null,
        public string $deviceName = '',
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $androidVersion = null,
        public ?string $appVersion = null,
        public ?string $fcmToken = null,
        public ?array $capabilities = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            androidId: $data['android_id'],
            uuid: $data['uuid'] ?? null,
            deviceName: $data['device_name'] ?? 'أندرويد كاش هونكس',
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            androidVersion: $data['android_version'] ?? null,
            appVersion: $data['app_version'] ?? null,
            fcmToken: $data['fcm_token'] ?? null,
            capabilities: $data['capabilities'] ?? null
        );
    }
}

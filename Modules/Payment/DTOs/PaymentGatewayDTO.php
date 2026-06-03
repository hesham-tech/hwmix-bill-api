<?php

namespace Modules\Payment\DTOs;

// تعليق عربي: كائن نقل البيانات (DTO) الخاص بإعدادات بوابة الدفع لتوحيد نقل البيانات بين الكنترولر والأكشن.

class PaymentGatewayDTO
{
    public function __construct(
        public string $name,
        public string $driver,
        public array $config,
        public bool $isActive = true,
        public bool $isTestMode = false,
        public bool $isDefault = false
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            driver: $data['driver'],
            config: $data['config'] ?? [],
            isActive: (bool) ($data['is_active'] ?? true),
            isTestMode: (bool) ($data['is_test_mode'] ?? false),
            isDefault: (bool) ($data['is_default'] ?? false)
        );
    }
}

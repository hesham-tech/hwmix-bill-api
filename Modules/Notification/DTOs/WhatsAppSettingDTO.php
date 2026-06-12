<?php

namespace Modules\Notification\DTOs;

//   كائن نقل البيانات (DTO) لإعدادات الواتساب لتوحيد نقل البيانات وحمايتها بين المتحكم والأكشن.

class WhatsAppSettingDTO
{
    public function __construct(
        public ?int $id,
        public string $title,
        public string $phone_number_id,
        public string $waba_id,
        public ?string $access_token,
        public bool $is_active = true,
        public bool $is_default = false,
        public bool $is_global = false
    ) {
    }

    /**
     * إنشاء DTO من مصفوفة البيانات القادمة من الطلب (Request).
     */
    public static function fromRequest(array $data, ?int $id = null): self
    {
        return new self(
            id: $id ?: ($data['id'] ?? null),
            title: $data['title'],
            phone_number_id: $data['phone_number_id'],
            waba_id: $data['waba_id'],
            access_token: $data['access_token'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true),
            is_default: (bool) ($data['is_default'] ?? false),
            is_global: (bool) ($data['is_global'] ?? false)
        );
    }

    /**
     * تحويل الكائن إلى مصفوفة لحفظها في قاعدة البيانات.
     */
    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'phone_number_id' => $this->phone_number_id,
            'waba_id' => $this->waba_id,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_global' => $this->is_global,
        ];

        if (!empty($this->access_token)) {
            $data['access_token'] = $this->access_token;
        }

        return $data;
    }
}

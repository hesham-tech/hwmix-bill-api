<?php

namespace Modules\Core\DTOs;

/**
 * الكلاس الأساسي لنقل البيانات (Data Transfer Objects)
 */
abstract class BaseDTO
{
    /**
     * تحويل الـ DTO إلى مصفوفة.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * إنشاء DTO من مصفوفة.
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }
        return $instance;
    }
}

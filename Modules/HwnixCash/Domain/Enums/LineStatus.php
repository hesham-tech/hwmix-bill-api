<?php
// تعديد يمثل حالات خطوط الاتصال وشرائح الـ SIM.

namespace Modules\HwnixCash\Domain\Enums;

enum LineStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case NO_SIGNAL = 'no_signal';
}

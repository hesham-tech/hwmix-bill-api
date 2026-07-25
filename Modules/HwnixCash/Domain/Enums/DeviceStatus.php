<?php
// تعديد يمثل حالات تشغيل وتواجد أجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Enums;

enum DeviceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MAINTENANCE = 'maintenance';
    case UNBOUND = 'unbound';
}

<?php
// حالة تشغيل هاتف الأندرويد المسجل بالنظام.

namespace Modules\SmsGateway\Domain\Enums;

enum DeviceStatus: string
{
    case Active = 'active';
    case Offline = 'offline';
    case Paused = 'paused';
}

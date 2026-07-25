<?php
// تعديد يمثل حالات تنفيذ الأوامر التشغيلية لأجهزة كاش هونكس.

namespace Modules\HwnixCash\Domain\Enums;

enum CommandStatus: string
{
    case PENDING = 'pending';
    case EXECUTED = 'executed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}

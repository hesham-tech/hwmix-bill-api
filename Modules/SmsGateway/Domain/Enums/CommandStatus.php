<?php
// حالة تنفيذ الأوامر المرسلة إلى هاتف الأندرويد.

namespace Modules\SmsGateway\Domain\Enums;

enum CommandStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Executed = 'executed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

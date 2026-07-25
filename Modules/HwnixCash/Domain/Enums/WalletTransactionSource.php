<?php
// التعداد المخصص لمصدر إنشاء معاملة المحفظة الإلكترونية.

namespace Modules\HwnixCash\Domain\Enums;

enum WalletTransactionSource: string
{
    case SMS = 'sms';
    case API = 'api';
    case MANUAL = 'manual';
    case IMPORT = 'import';
    case SYSTEM = 'system';
}

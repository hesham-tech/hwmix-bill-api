<?php
// التعداد المخصص لحالة تنفيذ معاملة المحفظة الإلكترونية.

namespace Modules\HwnixCash\Domain\Enums;

enum WalletTransactionStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case REVERSED = 'reversed';
}

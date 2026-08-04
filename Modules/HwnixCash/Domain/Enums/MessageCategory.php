<?php
// إينوم فئات الرسائل في النظام (معاملة مالية، دعاية، إشعار، نظام).

namespace Modules\HwnixCash\Domain\Enums;

enum MessageCategory: string
{
    case TRANSACTION = 'transaction';
    case PROMOTION = 'promotion';
    case NOTIFICATION = 'notification';
    case SYSTEM = 'system';
}

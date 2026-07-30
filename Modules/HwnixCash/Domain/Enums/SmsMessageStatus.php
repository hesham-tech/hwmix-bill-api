<?php
// تعديد حالات الإرسال والمعالجة للرسائل النصية لكاش هونكس.

namespace Modules\HwnixCash\Domain\Enums;

enum SmsMessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RECEIVED = 'received';
    case PROCESSED = 'processed';
    case NEEDS_REVIEW = 'needs_review';
    case IGNORED = 'ignored';
}

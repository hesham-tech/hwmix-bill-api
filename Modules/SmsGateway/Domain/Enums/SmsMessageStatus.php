<?php
// حالة رسالة الـ SMS الموحدة عبر دورة حياتها بالكامل.

namespace Modules\SmsGateway\Domain\Enums;

enum SmsMessageStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

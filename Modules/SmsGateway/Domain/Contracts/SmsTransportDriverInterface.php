<?php
// واجهة تشغيل سائق النقل (Driver) لإرسال رسائل الـ SMS.

namespace Modules\SmsGateway\Domain\Contracts;

use Modules\SmsGateway\Domain\Entities\SmsMessage;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;

interface SmsTransportDriverInterface
{
    /**
     * إرسال رسالة SMS باستخدام وسيط النقل المحدد.
     *
     * @param SmsMessage $message
     * @return SmsMessageStatus
     */
    public function send(SmsMessage $message): SmsMessageStatus;
}

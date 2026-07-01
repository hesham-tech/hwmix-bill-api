<?php
// سائق إرسال بوابات أجهزة الأندرويد (Android Agent Driver).

namespace Modules\SmsGateway\Drivers;

use Modules\SmsGateway\Domain\Contracts\SmsTransportDriverInterface;
use Modules\SmsGateway\Domain\Entities\SmsMessage;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;
use Modules\SmsGateway\Domain\Enums\CommandStatus;
use Modules\SmsGateway\Models\SmsDeviceCommand;
use Modules\SmsGateway\Models\SmsLine;

class AndroidAgentDriver implements SmsTransportDriverInterface
{
    /**
     * إرسال رسالة SMS عبر إدراج أمر تشغيل الأندرويد وإطلاق FCM.
     */
    public function send(SmsMessage $message): SmsMessageStatus
    {
        // 1. جلب الشريحة النشطة لمعرفة الـ slot ومعرف الاشتراك
        $line = SmsLine::findOrFail($message->lineId);

        // 2. إنشاء أمر إرسال SEND_SMS للجهاز في قاعدة البيانات
        SmsDeviceCommand::create([
            'sms_device_id' => $message->deviceId,
            'command_type' => 'SEND_SMS',
            'payload' => [
                'message_id' => $message->id,
                'phone_number' => $message->phoneNumber,
                'message_body' => $message->messageBody,
                'slot_index' => $line->slot_index,
                'subscription_id' => $line->subscription_id,
            ],
            'status' => CommandStatus::Pending->value,
            'idempotency_key' => 'SEND_SMS_MSG_' . $message->id,
        ]);

        // 3. إشعار الجهاز المطلوب عبر قناة FCM صامتة للاستيقاظ الفوري
        $this->dispatchFcmWakeup($message->deviceId);

        return SmsMessageStatus::Queued;
    }

    /**
     * إرسال إشعار FCM صامت لإيقاظ تطبيق الأندرويد في الخلفية لجلب الأوامر المعلقة.
     */
    private function dispatchFcmWakeup(int $deviceId): void
    {
        // مستقبلاً يتم الربط مع خدمة الإشعارات لإرسال إشارة FCM Data Message عالية الأولوية
        \Log::info("FCM Silent wakeup dispatched for device ID: {$deviceId} via AndroidAgentDriver");
    }
}

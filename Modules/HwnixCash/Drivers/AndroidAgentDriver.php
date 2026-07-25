<?php
// سائق إرسال رسائل كاش هونكس عبر تطبيق الأندرويد للأجهزة المربوطة.

namespace Modules\HwnixCash\Drivers;

use Modules\HwnixCash\Domain\Contracts\HwnixCashTransportDriverInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Models\HwnixCashDeviceCommand;
use Modules\HwnixCash\Models\HwnixCashLine;

class AndroidAgentDriver implements HwnixCashTransportDriverInterface
{
    public function send(SmsMessage $message): bool
    {
        $line = HwnixCashLine::find($message->smsLineId);
        if (!$line) {
            return false;
        }

        $device = $line->device;
        if (!$device) {
            return false;
        }

        // إنشاء أمر SEND_SMS بالهاتف
        HwnixCashDeviceCommand::create([
            'sms_device_id' => $device->id,
            'command_type' => 'SEND_SMS',
            'payload' => [
                'message_id' => $message->id,
                'phone_number' => $message->phoneNumber,
                'message_body' => $message->messageBody,
                'slot_index' => $line->slot_index,
                'subscription_id' => $line->subscription_id,
            ],
            'status' => 'pending',
            'idempotency_key' => 'send_msg_' . $message->id,
        ]);

        return true;
    }

    public function getDriverName(): string
    {
        return 'android';
    }
}

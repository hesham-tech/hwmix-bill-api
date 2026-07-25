<?php
// إجراء رد الاعتبار وتحديث نتائج الأوامر الموجهة للهواتف.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\Models\HwnixCashDeviceCommand;
use Modules\HwnixCash\Models\HwnixCashMessage;

class ExecuteCommandAction
{
    public function execute(int $commandId, int $deviceId, string $status, ?array $responsePayload): void
    {
        $command = HwnixCashDeviceCommand::where('id', $commandId)
            ->where('sms_device_id', $deviceId)
            ->first();

        if (!$command) {
            return;
        }

        $command->update([
            'status' => $status,
            'response_payload' => $responsePayload,
            'executed_at' => now(),
        ]);

        // ربط تحديث حالة الرسائل إن وجد
        if ($command->command_type === 'SEND_SMS' && !empty($command->payload['message_id'])) {
            $msgId = $command->payload['message_id'];
            $msgStatus = $status === 'executed' ? 'sent' : 'failed';
            HwnixCashMessage::where('id', $msgId)->update([
                'status' => $msgStatus,
                'sent_at' => $status === 'executed' ? now() : null,
            ]);
        }
    }
}

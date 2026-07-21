<?php
// متحكم لإدارة الأوامر التشغيلية وتوصيلها للهاتف وتحديث نتائج تنفيذها.

namespace Modules\SmsGateway\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Domain\Enums\CommandStatus;
use Modules\SmsGateway\Models\SmsDeviceCommand;

class AgentCommandController extends Controller
{
    /**
     * جلب الأوامر المعلقة الموجهة لهذا الجهاز.
     */
    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
        ]);

        // جلب الأوامر المعلقة: الأوامر بوضعية pending أو التي بوضعية sending وتجاوزت 5 دقائق دون تحديث
        $commands = SmsDeviceCommand::where('sms_device_id', $validated['device_id'])
            ->where(function($query) {
                $query->where('status', CommandStatus::Pending->value)
                      ->orWhere(function($q) {
                          $q->where('status', CommandStatus::Sending->value)
                            ->where('updated_at', '<', now()->subMinutes(5));
                      });
            })
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get();

        $formatted = [];
        foreach ($commands as $command) {
            // تحقق إضافي لمنع تكرار إرسال الرسائل الناجحة أو الفاشلة بالفعل
            if ($command->command_type === 'SEND_SMS' && isset($command->payload['message_id'])) {
                $message = \Modules\SmsGateway\Models\SmsMessage::find($command->payload['message_id']);
                if ($message && in_array($message->status, [
                    \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Sent->value,
                    \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Delivered->value,
                    \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Failed->value
                ])) {
                    // إذا تم إرسال الرسالة أو فشلها سابقاً، يتم استبعاد الأمر وتحديث حالته بالسيرفر
                    $statusValue = $message->status === \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Failed->value 
                        ? CommandStatus::Failed->value 
                        : CommandStatus::Executed->value;
                    $command->update([
                        'status' => $statusValue,
                        'executed_at' => now(),
                    ]);
                    continue;
                }
            }

            // تحديث حالة الأمر المعلق إلى sending
            if ($command->status === CommandStatus::Pending->value) {
                $command->update(['status' => CommandStatus::Sending->value]);
            }

            $formatted[] = [
                'id' => $command->id,
                'command_type' => $command->command_type,
                'payload' => $command->payload,
            ];
        }

        \Log::info("Pending commands request for device {$validated['device_id']}: found " . count($formatted) . " commands", ['commands' => array_column($formatted, 'id')]);

        return api_success($formatted, 'تم جلب الأوامر المعلقة بنجاح.');
    }

    /**
     * تحديث نتيجة تنفيذ الأمر على الهاتف.
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'status' => 'required|string|in:executed,failed',
            'response_payload' => 'nullable|array',
        ]);

        $command = SmsDeviceCommand::where('id', $id)
            ->where('sms_device_id', $validated['device_id'])
            ->first();

        if (!$command) {
            return api_error('لم يتم العثور على الأمر المحدد للجهاز.', [], 404);
        }

        \Log::info("Command {$id} execution update received: status={$validated['status']}", ['device_id' => $validated['device_id']]);

        // تحديث حالة الأمر
        $statusValue = $validated['status'] === 'executed' ? CommandStatus::Executed->value : CommandStatus::Failed->value;
        
        $command->update([
            'status' => $statusValue,
            'response_payload' => $validated['response_payload'] ?? null,
            'executed_at' => now(),
        ]);

        // إطلاق أحداث بناءً على نوع الأمر والتنفيذ
        if ($command->command_type === 'SEND_SMS' && isset($command->payload['message_id'])) {
            $msgStatus = $validated['status'] === 'executed' ? \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Sent : \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Failed;
            $reason = $validated['response_payload']['error'] ?? null;
            
            // تحديث حالة الرسالة المقابلة
            $msgRepo = app(\Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface::class);
            $msgRepo->updateStatus($command->payload['message_id'], $msgStatus, $reason);

            // إطلاق أحداث الإرسال
            if ($msgStatus === \Modules\SmsGateway\Domain\Enums\SmsMessageStatus::Sent) {
                event(new \Modules\SmsGateway\Events\SmsSent($command->payload['message_id']));
            } else {
                event(new \Modules\SmsGateway\Events\SmsFailed($command->payload['message_id'], $reason));
            }
        }

        // إطلاق حدث إتمام تنفيذ أمر مخصص
        event(new \Modules\SmsGateway\Events\CommandExecuted($command));

        return api_success(null, 'تم تحديث حالة تنفيذ الأمر بنجاح.');
    }
}

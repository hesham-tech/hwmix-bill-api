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

        // جلب الأوامر المعلقة
        $commands = SmsDeviceCommand::where('sms_device_id', $validated['device_id'])
            ->whereIn('status', [CommandStatus::Pending->value, CommandStatus::Sending->value])
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get();

        // تحديث حالة الأوامر إلى sending للإشارة إلى استلامها من الهاتف
        foreach ($commands as $command) {
            if ($command->status === CommandStatus::Pending->value) {
                $command->update(['status' => CommandStatus::Sending->value]);
            }
        }

        $formatted = $commands->map(fn($cmd) => [
            'id' => $cmd->id,
            'command_type' => $cmd->command_type,
            'payload' => $cmd->payload,
        ]);

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

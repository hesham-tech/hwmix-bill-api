<?php
// متحكم لاستلام الرسائل الواردة من الهاتف وتحديثات حالة الرسائل والمزامنة الجماعية.

namespace Modules\SmsGateway\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Services\SmsDispatcherService;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;
use Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface;

class AgentSmsController extends Controller
{
    public function __construct(
        protected SmsDispatcherService $dispatcherService,
        protected SmsMessageRepositoryInterface $messageRepo
    ) {}

    /**
     * استقبال رسالة واردة جديدة من الهاتف.
     */
    public function incoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'subscription_id' => 'required|string',
            'phone_number' => 'required|string',
            'message_body' => 'required|string',
            'message_ref' => 'required|string', // المعرف المحلي بالهاتف
            'sent_at' => 'nullable|string',
        ]);

        $user = $request->user();
        $message = $this->dispatcherService->processIncomingSms($validated, $user->active_company_id, $user->id);

        return api_success([
            'message_id' => $message->id,
            'status' => $message->status->value,
        ], 'تم مزامنة الرسالة الواردة بنجاح.');
    }

    /**
     * تحديث حالة رسالة صادرة من الهاتف.
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required|integer',
            'device_id' => 'required|integer',
            'status' => 'required|string|in:sent,delivered,failed',
            'failure_reason' => 'nullable|string',
        ]);

        $statusEnum = SmsMessageStatus::from($validated['status']);
        
        // التحقق من ملكية الرسالة للجهاز قبل التحديث
        $message = $this->messageRepo->findById($validated['message_id']);
        if (!$message || $message->deviceId !== $validated['device_id']) {
            return api_error('الرسالة غير متوفرة أو لا تتبع هذا الجهاز.', [], 404);
        }

        $this->messageRepo->updateStatus($validated['message_id'], $statusEnum, $validated['failure_reason']);

        // إطلاق أحداث الحالة
        if ($statusEnum === SmsMessageStatus::Sent) {
            event(new \Modules\SmsGateway\Events\SmsSent($validated['message_id']));
        } elseif ($statusEnum === SmsMessageStatus::Failed) {
            event(new \Modules\SmsGateway\Events\SmsFailed($validated['message_id'], $validated['failure_reason']));
        }

        return api_success(null, 'تم تحديث حالة الرسالة بنجاح.');
    }

    /**
     * مزامنة جماعية للرسائل الواردة المتراكمة محلياً بالهاتف عند عودة الاتصال.
     */
    public function batchSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'messages' => 'required|array',
            'messages.*.subscription_id' => 'required|string',
            'messages.*.phone_number' => 'required|string',
            'messages.*.message_body' => 'required|string',
            'messages.*.message_ref' => 'required|string',
            'messages.*.sent_at' => 'nullable|string',
        ]);

        $user = $request->user();
        $syncedIds = [];

        foreach ($validated['messages'] as $msgData) {
            try {
                $msgData['device_id'] = $validated['device_id'];
                $message = $this->dispatcherService->processIncomingSms($msgData, $user->active_company_id, $user->id);
                $syncedIds[] = [
                    'message_ref' => $msgData['message_ref'],
                    'message_id' => $message->id,
                ];
            } catch (\Exception $e) {
                \Log::error("Failed to sync message in batch: " . $e->getMessage());
            }
        }

        return api_success([
            'synced' => $syncedIds,
        ], 'تمت المزامنة الجماعية للرسائل بنجاح.');
    }
}

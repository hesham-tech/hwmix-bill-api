<?php
// متحكم استلام وتأكيد حالة الرسائل من وإلى هواتف الأندرويد.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\HwnixCash\Actions\ProcessIncomingSmsAction;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\Http\Requests\Agent\BatchSyncSmsRequest;
use Modules\HwnixCash\Http\Requests\Agent\IncomingSmsRequest;
use Modules\HwnixCash\Http\Requests\Agent\SyncStatusSmsRequest;
use Modules\HwnixCash\Models\HwnixCashMessage;

class AgentSmsController extends Controller
{
    public function __construct(
        protected ProcessIncomingSmsAction $processIncomingSmsAction
    ) {}

    public function incoming(IncomingSmsRequest $request): JsonResponse
    {
        $user = $request->user();
        $dto = IncomingSmsData::fromArray($request->validated());

        $message = $this->processIncomingSmsAction->execute($dto, $user->company_id, $user->id);

        return api_success([
            'message_id' => $message->id,
            'status' => $message->status->value,
        ], 'تم حفظ الرسالة الواردة بنجاح.');
    }

    public function syncStatus(SyncStatusSmsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $message = HwnixCashMessage::find($validated['message_id']);
        if ($message) {
            $message->update([
                'status' => $validated['status'],
                'error_code' => $validated['error_code'] ?? null,
                'error_message' => $validated['error_message'] ?? null,
                'sent_at' => $validated['status'] === 'sent' || $validated['status'] === 'delivered' ? now() : null,
            ]);
        }

        return api_success(null, 'تم تحديث حالة تسليم الرسالة بنجاح.');
    }

    public function batchSync(BatchSyncSmsRequest $request): JsonResponse
    {
        $user = $request->user();
        $messages = $request->validated()['messages'];

        foreach ($messages as $msg) {
            $dto = IncomingSmsData::fromArray($msg);
            $this->processIncomingSmsAction->execute($dto, $user->company_id, $user->id);
        }

        return api_success(null, 'تمت مزامنة دفعة الرسائل بنجاح.');
    }
}

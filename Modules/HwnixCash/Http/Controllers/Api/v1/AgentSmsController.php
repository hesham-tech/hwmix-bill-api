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
        \Log::info('📥 [AgentSmsController@incoming] Received SMS from Agent Device', [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'ip' => $request->ip(),
            'payload' => $request->validated(),
        ]);

        $dto = IncomingSmsData::fromArray($request->validated());

        $message = $this->processIncomingSmsAction->execute($dto, $user->company_id, $user->id);

        \Log::info('✅ [AgentSmsController@incoming] SMS processed successfully', [
            'message_id' => $message->id,
            'status' => $message->status->value,
        ]);

        return api_success([
            'message_id' => $message->id,
            'status' => $message->status->value,
        ], 'تم حفظ الرسالة الواردة بنجاح.');
    }

    public function syncStatus(SyncStatusSmsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        \Log::info('🔄 [AgentSmsController@syncStatus] Updating SMS status', [
            'payload' => $validated,
        ]);

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
        $validated = $request->validated();
        $deviceId = (int) $validated['device_id'];
        $messages = $validated['messages'];

        \Log::info('📦 [AgentSmsController@batchSync] Processing SMS Batch', [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'device_id' => $deviceId,
            'count' => count($messages),
            'ip' => $request->ip(),
            'messages_sample' => array_slice($messages, 0, 3),
        ]);

        $processedCount = 0;
        foreach ($messages as $msg) {
            $msg['device_id'] = $deviceId;
            $dto = IncomingSmsData::fromArray($msg, $deviceId);
            $this->processIncomingSmsAction->execute($dto, $user->company_id, $user->id, true);
            $processedCount++;
        }

        \Log::info('✅ [AgentSmsController@batchSync] SMS Batch completed', [
            'processed_count' => $processedCount,
        ]);

        $device = \Modules\HwnixCash\Models\HwnixCashDevice::find($deviceId);
        $linesSummary = [];
        if ($device) {
            $lines = \Modules\HwnixCash\Models\HwnixCashLine::where('device_android_id', $device->android_id)
                ->with('financialAccounts')
                ->get();

            foreach ($lines as $line) {
                $dailyDepositLimit = 0.0;
                $dailyDepositUsed = 0.0;
                $monthlyDepositLimit = 0.0;
                $monthlyDepositUsed = 0.0;

                foreach ($line->financialAccounts as $acc) {
                    $dailyDepositLimit += (float) ($acc->daily_deposit_limit ?? 0);
                    $dailyDepositUsed += (float) $acc->daily_deposit_used;
                    $monthlyDepositLimit += (float) ($acc->monthly_deposit_limit ?? 0);
                    $monthlyDepositUsed += (float) $acc->monthly_deposit_used;
                }

                $linesSummary[] = [
                    'slot_index' => (int) $line->slot_index,
                    'phone_number' => $line->phone_number,
                    'carrier' => $line->carrier,
                    'total_balance' => (float) $line->total_balance,
                    'total_actual_balance' => (float) $line->total_actual_balance,
                    'daily_deposit_limit' => $dailyDepositLimit,
                    'daily_deposit_used' => $dailyDepositUsed,
                    'monthly_deposit_limit' => $monthlyDepositLimit,
                    'monthly_deposit_used' => $monthlyDepositUsed,
                ];
            }
        }

        return api_success([
            'processed_count' => $processedCount,
            'lines_summary' => $linesSummary,
        ], 'تمت مزامنة دفعة الرسائل بنجاح.');
    }
}

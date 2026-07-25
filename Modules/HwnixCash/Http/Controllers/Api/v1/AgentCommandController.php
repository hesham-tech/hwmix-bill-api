<?php
// متحكم معالجة وتنفيذ أجهزة الأندرويد للأوامر التشغيلية الموجهة من السيرفر.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\HwnixCash\Actions\ExecuteCommandAction;
use Modules\HwnixCash\Http\Requests\Agent\ExecuteCommandRequest;
use Modules\HwnixCash\Http\Requests\Agent\GetPendingCommandsRequest;
use Modules\HwnixCash\Models\HwnixCashDeviceCommand;
use Modules\HwnixCash\Transformers\DeviceCommandResource;

class AgentCommandController extends Controller
{
    public function __construct(
        protected ExecuteCommandAction $executeCommandAction
    ) {}

    public function getPendingCommands(GetPendingCommandsRequest $request): JsonResponse
    {
        $deviceId = $request->validated()['device_id'];

        $commands = HwnixCashDeviceCommand::where('sms_device_id', $deviceId)
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->get();

        return api_success(DeviceCommandResource::collection($commands), 'تم جلب قائمة الأوامر المعلقة بنجاح.');
    }

    public function execute(ExecuteCommandRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $this->executeCommandAction->execute(
            commandId: $id,
            deviceId: $validated['device_id'],
            status: $validated['status'],
            responsePayload: $validated['response_payload'] ?? null
        );

        return api_success(null, 'تم تحديث حالة تنفيذ الأمر بنجاح.');
    }
}

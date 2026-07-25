<?php
// متحكم إدارة تسجيل الأجهزة ومزامنة الخطوط والمحافظ ونبضات التشغيل للهواتف.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Actions\RecordHeartbeatAction;
use Modules\HwnixCash\Actions\RegisterDeviceAction;
use Modules\HwnixCash\Actions\SyncSimLinesAction;
use Modules\HwnixCash\DTOs\DeviceData;
use Modules\HwnixCash\DTOs\HeartbeatData;
use Modules\HwnixCash\DTOs\LineData;
use Modules\HwnixCash\Http\Requests\Agent\DecoupleDeviceRequest;
use Modules\HwnixCash\Http\Requests\Agent\GetConfigRequest;
use Modules\HwnixCash\Http\Requests\Agent\GetLinesRequest;
use Modules\HwnixCash\Http\Requests\Agent\HeartbeatRequest;
use Modules\HwnixCash\Http\Requests\Agent\LogRequest;
use Modules\HwnixCash\Http\Requests\Agent\RegisterDeviceRequest;
use Modules\HwnixCash\Http\Requests\Agent\SyncLinesRequest;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Transformers\DeviceResource;
use Modules\HwnixCash\Transformers\LineResource;

class AgentDeviceController extends Controller
{
    public function __construct(
        protected RegisterDeviceAction $registerDeviceAction,
        protected SyncSimLinesAction $syncSimLinesAction,
        protected RecordHeartbeatAction $recordHeartbeatAction
    ) {}

    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $dto = DeviceData::fromArray($request->validated());

        $device = $this->registerDeviceAction->execute($dto, $user->company_id, $user->id);

        return api_success([
            'device_id' => $device->id,
            'status' => $device->status->value,
            'settings' => [
                'heartbeat_interval' => 60,
                'max_retry' => 3,
            ]
        ], 'تم تسجيل الجهاز بنجاح.');
    }

    public function syncLines(SyncLinesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $sims = array_map(fn($item) => LineData::fromArray($item), $validated['sims']);

        $this->syncSimLinesAction->execute(
            deviceId: $validated['device_id'],
            deviceName: $validated['device_name'] ?? '',
            sims: $sims,
            companyId: $user->company_id,
            userId: $user->id
        );

        return api_success(null, 'تمت مزامنة الخطوط بنجاح.');
    }

    public function heartbeat(HeartbeatRequest $request): JsonResponse
    {
        $dto = HeartbeatData::fromArray($request->validated());
        $this->recordHeartbeatAction->execute($dto);

        return api_success([
            'settings_updated' => false,
            'update_policy' => 'none',
        ], 'تم تسجيل نبضة التشغيل بنجاح.');
    }

    public function getLines(GetLinesRequest $request): JsonResponse
    {
        $deviceId = $request->validated()['device_id'];
        $device = HwnixCashDevice::find($deviceId);

        if (!$device) {
            return api_error('الجهاز غير مسجل.', [], 404);
        }

        $lines = HwnixCashLine::where('device_android_id', $device->android_id)->get();

        return api_success(LineResource::collection($lines), 'تم جلب قائمة الخطوط بنجاح.');
    }

    public function getConfig(GetConfigRequest $request): JsonResponse
    {
        $deviceId = $request->validated()['device_id'];
        $device = HwnixCashDevice::find($deviceId);

        return api_success([
            'device' => $device ? new DeviceResource($device) : null,
            'heartbeat_interval' => 60,
        ], 'تم جلب إعدادات الجهاز بنجاح.');
    }

    public function log(LogRequest $request): JsonResponse
    {
        return api_success(null, 'تم حفظ السجلات بنجاح.');
    }

    public function decouple(DecoupleDeviceRequest $request): JsonResponse
    {
        $deviceId = $request->validated()['device_id'];
        HwnixCashDevice::where('id', $deviceId)->update(['status' => 'unbound']);

        return api_success(null, 'تم إلغاء ربط الجهاز بنجاح.');
    }

    public function checkAppUpdate(Request $request): JsonResponse
    {
        return api_success([
            'has_update' => false,
            'latest_version' => '1.0.0',
            'download_url' => route('hwnix-cash.downloads.app'),
            'force_update' => false,
        ], 'لا يوجد تحديثات جديدة حالياً.');
    }
}

<?php
// متحكم لإدارة تسجيل الهواتف ومزامنة الشرائح وسحب التكوينات ونبضات التشغيل.

namespace Modules\SmsGateway\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Services\SmsGatewayService;

class AgentDeviceController extends Controller
{
    public function __construct(
        protected SmsGatewayService $gatewayService
    ) {}

    /**
     * تسجيل أو تحديث الهاتف.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'android_id' => 'required|string',
            'uuid' => 'required|string',
            'device_name' => 'required|string',
            'brand' => 'required|string',
            'model' => 'required|string',
            'android_version' => 'required|string',
            'app_version' => 'required|string',
            'capabilities' => 'nullable|array',
        ]);

        $user = $request->user();
        $device = $this->gatewayService->registerDevice($validated, $user->active_company_id, $user->id);

        return api_success([
            'device_id' => $device->id,
            'status' => $device->status->value,
        ], 'تم تسجيل الجهاز بنجاح.');
    }

    /**
     * مزامنة الشرائح المتاحة بالهاتف.
     */
    public function syncLines(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'sims' => 'required|array',
            'sims.*.slot_index' => 'required|integer',
            'sims.*.subscription_id' => 'required|string',
            'sims.*.carrier' => 'required|string',
            'sims.*.mcc' => 'nullable|string',
            'sims.*.mnc' => 'nullable|string',
            'sims.*.phone_number' => 'nullable|string',
            'sims.*.network_type' => 'nullable|string',
            'sims.*.signal_strength' => 'nullable|integer',
        ]);

        $user = $request->user();
        $this->gatewayService->syncSimLines($validated['device_id'], $validated['sims'], $user->active_company_id, $user->id);

        return api_success(null, 'تم مزامنة الشرائح بنجاح.');
    }

    /**
     * استقبال النبضات وإرجاع الإعدادات المحدثة وتحديثات التطبيق.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'network_type' => 'nullable|string',
            'battery_level' => 'required|integer',
            'is_internet_available' => 'required|boolean',
            'free_memory_bytes' => 'nullable|integer',
            'free_storage_bytes' => 'nullable|integer',
            'app_version' => 'required|string',
            'configuration_version' => 'required|integer', // الإصدار المحلي بالهاتف
        ]);

        $result = $this->gatewayService->recordHeartbeat($validated['device_id'], $validated);

        $response = [
            'settings_updated' => false,
            'update_policy' => $result['update_policy'],
        ];

        // لا نرسل التكوين بالكامل إلا إذا كان هناك إصدار أحدث على السيرفر
        if ($result['config'] && $result['config']->configuration_version > $validated['configuration_version']) {
            $response['settings_updated'] = true;
            $response['config'] = [
                'configuration_version' => $result['config']->configuration_version,
                'polling_interval_seconds' => $result['config']->polling_interval_seconds,
                'max_retry_count' => $result['config']->max_retry_count,
                'logging_level' => $result['config']->logging_level,
                'feature_flags' => $result['config']->feature_flags,
                'sync_limits' => $result['config']->sync_limits,
            ];
        }

        return api_success($response, 'تم استلام النبضة بنجاح.');
    }

    /**
     * الحصول على الإعدادات التشغيلية يدوياً.
     */
    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
        ]);

        $settings = \Modules\SmsGateway\Models\SmsDeviceSetting::where('sms_device_id', $validated['device_id'])->first();
        if (!$settings) {
            return api_error('لم يتم العثور على إعدادات لهذا الجهاز.', [], 404);
        }

        return api_success([
            'configuration_version' => $settings->configuration_version,
            'polling_interval_seconds' => $settings->polling_interval_seconds,
            'max_retry_count' => $settings->max_retry_count,
            'logging_level' => $settings->logging_level,
            'feature_flags' => $settings->feature_flags,
            'sync_limits' => $settings->sync_limits,
        ], 'تم جلب الإعدادات بنجاح.');
    }

    /**
     * التحقق من وجود تحديث جديد لتطبيق الأندرويد.
     */
    public function checkAppUpdate(Request $request): JsonResponse
    {
        $versionCode = 10; // رقم إصدار الـ APK المتوفر حالياً على السيرفر
        $versionName = "1.0.9";
        $downloadUrl = url('downloads/sms-agent-v1.0.9.apk');

        return api_success([
            'version_code' => $versionCode,
            'version_name' => $versionName,
            'download_url' => $downloadUrl,
            'changelog' => 'معالجة مشكلة تعليق التطبيق وتصحيح رابط الباك إند وتحديث التوقيع والأيقونات.',
            'force_update' => true
        ], 'معلومات التحديث المتاحة.');
    }
}

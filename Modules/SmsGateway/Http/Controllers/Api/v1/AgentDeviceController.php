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
        $device = $this->gatewayService->registerDevice($validated, $user->company_id, $user->id);

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
        if ($decoupled = $this->checkDeviceStatus((int)$request->input('device_id'))) {
            return $decoupled;
        }

        $validated = $request->validate([
            'device_id' => 'required|integer|exists:smsgate_devices,id',
            'device_name' => 'nullable|string',
            'sims' => 'required|array',
            'sims.*.slot_index' => 'required|integer',
            'sims.*.subscription_id' => 'required|string',
            'sims.*.carrier' => 'nullable|string',
            'sims.*.mcc' => 'nullable|string',
            'sims.*.mnc' => 'nullable|string',
            'sims.*.phone_number' => 'nullable|string',
            'sims.*.network_type' => 'nullable|string',
            'sims.*.signal_strength' => 'nullable|integer',
        ]);

        $user = $request->user();
        $this->gatewayService->syncSimLines(
            $validated['device_id'],
            $validated['sims'],
            $user->company_id,
            $user->id,
            $validated['device_name'] ?? null
        );

        return api_success(null, 'تم مزامنة الشرائح بنجاح.');
    }

    /**
     * استقبال النبضات وإرجاع الإعدادات المحدثة وتحديثات التطبيق.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if ($decoupled = $this->checkDeviceStatus((int)$request->input('device_id'))) {
            return $decoupled;
        }

        $validated = $request->validate([
            'device_id' => 'required|integer|exists:smsgate_devices,id',
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
            'device_id' => 'required|integer|exists:smsgate_devices,id',
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
     * التحقق من وجود تحديث جديد لتطبيق الأندرويد dynamically.
     */
    public function checkAppUpdate(Request $request): JsonResponse
    {
        $jsonPath = public_path('downloads/app-version.json');
        $highestVersionCode = 1;
        $highestVersionName = "1.0.0";
        $downloadUrl = url('download-app/latest');

        if (file_exists($jsonPath)) {
            $jsonData = json_decode(file_get_contents($jsonPath), true);
            if (isset($jsonData['version_code']) && isset($jsonData['version_name'])) {
                $highestVersionCode = (int) $jsonData['version_code'];
                $highestVersionName = $jsonData['version_name'];
            }
        } else {
            $directory = public_path('downloads');
            if (is_dir($directory)) {
                $files = scandir($directory);
                foreach ($files as $file) {
                    if (preg_match('/^sms-agent-v([\d\.]+)\.apk$/i', $file, $matches)) {
                        $versionName = $matches[1];
                        // استنتاج رقم الكود بتحويل رقم الإصدار، مثل 1.0.11 -> 1011
                        $cleanVersion = str_replace('.', '', $versionName);
                        $versionCode = (int) $cleanVersion;

                        if ($versionCode > $highestVersionCode) {
                            $highestVersionCode = $versionCode;
                            $highestVersionName = $versionName;
                        }
                    }
                }
            }
        }

        return api_success([
            'version_code' => $highestVersionCode,
            'version_name' => $highestVersionName,
            'download_url' => $downloadUrl,
            'changelog' => 'دعم التشغيل التلقائي وإدارة الشرائح وإصلاحات الاستقرار.',
            'force_update' => true
        ], 'معلومات التحديث المتاحة.');
    }

    /**
     * تحميل أعلى إصدار متوفر من تطبيق الأندرويد تلقائياً.
     */
    public function downloadLatestApp()
    {
        $directory = public_path('downloads');
        if (!is_dir($directory)) {
            return response()->json(['message' => 'مجلد التحميلات غير موجود على السيرفر.'], 404);
        }

        $files = scandir($directory);
        $apkFiles = [];

        foreach ($files as $file) {
            if (preg_match('/^sms-agent-v([\d\.]+)\.apk$/i', $file, $matches)) {
                $version = $matches[1];
                $apkFiles[$version] = $directory . '/' . $file;
            }
        }

        if (empty($apkFiles)) {
            return response()->json(['message' => 'لا توجد أي إصدارات APK متوفرة للتحميل حالياً.'], 404);
        }

        // ترتيب المفاتيح (إصدارات التطبيق) ترتيباً تنازلياً للمقارنة السليمة
        uksort($apkFiles, function ($a, $b) {
            return version_compare($b, $a);
        });

        // الحصول على الملف ذو الإصدار الأعلى
        $latestVersion = array_key_first($apkFiles);
        $filePath = $apkFiles[$latestVersion];

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'ملف الإصدار الأخير غير موجود.'], 404);
        }

        return response()->download($filePath, "sms-agent-v{$latestVersion}.apk", [
            'Content-Type' => 'application/vnd.android.package-archive'
        ]);
    }

    /**
     * عرض صفحة تحميل الإصدارات المتوفرة لتطبيق الأندرويد.
     */
    public function showDownloadsPage()
    {
        $directory = public_path('downloads');
        $apkFiles = [];

        if (is_dir($directory)) {
            $files = scandir($directory);
            foreach ($files as $file) {
                if (preg_match('/^sms-agent-v([\d\.]+)\.apk$/i', $file, $matches)) {
                    $version = $matches[1];
                    $filePath = $directory . '/' . $file;
                    $apkFiles[] = [
                        'version' => $version,
                        'filename' => $file,
                        'size' => round(filesize($filePath) / (1024 * 1024), 2) . ' MB',
                        'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'url' => asset('downloads/' . $file)
                    ];
                }
            }
        }

        // ترتيب تنازلي حسب رقم الإصدار
        usort($apkFiles, function ($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        return view('smsgateway::downloads', compact('apkFiles'));
    }

    /**
     * الحصول على الشرائح المسجلة حالياً للجهاز.
     */
    public function getLines(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:smsgate_devices,id',
        ]);

        $device = \Modules\SmsGateway\Models\SmsDevice::findOrFail($validated['device_id']);
        $lines = $device->lines()
            ->where('status', \Modules\SmsGateway\Domain\Enums\LineStatus::Active->value)
            ->get();

        return api_success($lines, 'تم جلب خطوط الاتصال بنجاح.');
    }

    /**
     * إلغاء ربط وتسجيل الجهاز من طرف التطبيق نفسه.
     */
    public function decouple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
        ]);

        $this->gatewayService->decoupleDevice($validated['device_id']);

        return api_success(null, 'تم إلغاء ربط الجهاز بنجاح.');
    }

    /**
     * استقبال سجلات التشخيص والتتبع من الهاتف.
     */
    public function log(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'tag' => 'required|string',
            'message' => 'required|string',
            'details' => 'nullable|array',
        ]);

        \Log::info("Remote Diagnostic Log [Device: {$validated['device_id']}] Tag: [{$validated['tag']}]: {$validated['message']}", $validated['details'] ?? []);

        return api_success(null, 'تم حفظ سجل التشخيص بنجاح.');
    }

    /**
     * التحقق مما إذا كان الجهاز ملغى ربطه (موجود في المحذوفات مؤقتاً).
     */
    protected function checkDeviceStatus(int $deviceId): ?JsonResponse
    {
        $device = \Modules\SmsGateway\Models\SmsDevice::withTrashed()->find($deviceId);
        if ($device && $device->trashed()) {
            return response()->json([
                'status' => false,
                'message' => 'DEVICE_DECOUPLED',
                'errors' => ['device_id' => ['تم إلغاء ربط هذا الجهاز من لوحة التحكم.']]
            ], 403);
        }
        return null;
    }
}

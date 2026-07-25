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

    /**
     * التحقق من وجود تحديث جديد لتطبيق الأندرويد dynamically.
     */
    public function checkAppUpdate(Request $request): JsonResponse
    {
        $jsonPath = public_path('downloads/app-version.json');
        $highestVersionCode = 52;
        $highestVersionName = '1.0.52';
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
                    if (preg_match('/^(hwnix-cash|sms-agent)-v([a-zA-Z\d\.\-]+)\.apk$/i', $file, $matches)) {
                        $versionName = $matches[2];
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
            'changelog' => 'دعم استقرار وتجاوز قيود الواجهات التشغيلية لـ HWNix Cash.',
            'force_update' => false
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
            if (preg_match('/^(hwnix-cash|sms-agent)-v([a-zA-Z\d\.\-]+)\.apk$/i', $file, $matches)) {
                $version = $matches[2];
                $apkFiles[$version] = $directory . '/' . $file;
            }
        }

        if (empty($apkFiles)) {
            return response()->json(['message' => 'لا توجد أي إصدارات APK متوفرة للتحميل حالياً.'], 404);
        }

        uksort($apkFiles, function ($a, $b) {
            return version_compare($b, $a);
        });

        $latestVersion = array_key_first($apkFiles);
        $filePath = $apkFiles[$latestVersion];

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'ملف الإصدار الأخير غير موجود.'], 404);
        }

        return response()->download($filePath, basename($filePath), [
            'Content-Type' => 'application/vnd.android.package-archive'
        ]);
    }

    /**
     * عرض صفحة أو قائمة تحميل الإصدارات المتوفرة لتطبيق الأندرويد.
     */
    public function showDownloadsPage()
    {
        $directory = public_path('downloads');
        $apkFiles = [];

        if (is_dir($directory)) {
            $files = scandir($directory);
            foreach ($files as $file) {
                if (preg_match('/^(hwnix-cash|sms-agent)-v([a-zA-Z\d\.\-]+)\.apk$/i', $file, $matches)) {
                    $version = $matches[2];
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

        usort($apkFiles, function ($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => true,
                'message' => 'قائمة ملفات التطبيق المتوفرة للتحميل',
                'latest_download_url' => url('download-app/latest'),
                'files' => $apkFiles,
            ]);
        }

        return view('hwnixcash::downloads', compact('apkFiles'));
    }
}

<?php
// متحكم لإدارة أجهزة البوابة المفتوحة للوحة التحكم بالويب.

namespace Modules\SmsGateway\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Domain\Contracts\SmsDeviceRepositoryInterface;

class DeviceController extends Controller
{
    public function __construct(
        protected SmsDeviceRepositoryInterface $deviceRepo
    ) {}

    /**
     * عرض قائمة الأجهزة التابعة للشركة النشطة.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_gateway.view_all')) && !$user->hasPermissionTo(perm_key('sms_gateway.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض الأجهزة.');
        }

        $devices = $this->deviceRepo->getCompanyDevices($user->active_company_id);

        // تنسيق المخرجات
        $formatted = array_map(fn($dev) => [
            'id' => $dev->id,
            'device_name' => $dev->deviceName,
            'brand' => $dev->brand,
            'model' => $dev->model,
            'android_version' => $dev->androidVersion,
            'app_version' => $dev->appVersion,
            'status' => $dev->status->value,
            'capabilities' => $dev->capabilities,
            'last_seen_at' => $dev->lastSeenAt?->format('Y-m-d H:i:s'),
        ], $devices);

        return api_success($formatted, 'تم جلب قائمة الأجهزة بنجاح.');
    }

    /**
     * إلغاء ربط جهاز وحذفه.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_gateway.delete_all'))) {
            return api_forbidden('غير مصرح لك بحذف الأجهزة.');
        }

        $device = $this->deviceRepo->findById($id);
        if (!$device || $device->companyId !== $user->active_company_id) {
            return api_error('الجهاز غير متوفر أو لا ينتمي لشركتك.', [], 404);
        }

        $this->deviceRepo->delete($id);

        return api_success(null, 'تم إلغاء ربط وحذف الجهاز بنجاح.');
    }
}

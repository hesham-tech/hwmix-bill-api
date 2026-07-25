<?php
// متحكم لإدارة أجهزة كاش هونكس HwnixCash للوحة التحكم بالويب.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Domain\Contracts\HwnixCashDeviceRepositoryInterface;
use Modules\HwnixCash\Transformers\DeviceResource;

class DeviceController extends Controller
{
    public function __construct(
        protected HwnixCashDeviceRepositoryInterface $deviceRepo
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('hwnix_cash.view_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض أجهزة كاش هونكس.');
        }

        $devices = $this->deviceRepo->getCompanyDevices($user->company_id);

        return api_success(DeviceResource::collection($devices), 'تم جلب قائمة أجهزة كاش هونكس بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('hwnix_cash.delete_all'))) {
            return api_forbidden('غير مصرح لك بحذف الأجهزة.');
        }

        $device = $this->deviceRepo->findById($id);
        if (!$device || $device->companyId !== $user->company_id) {
            return api_error('الجهاز غير متوفر أو لا ينتمي لشركتك.', [], 404);
        }

        $this->deviceRepo->delete($id);

        return api_success(null, 'تم إلغاء ربط وحذف الجهاز بنجاح.');
    }
}

<?php

namespace Modules\Notification\Http\Controllers;

// تعليق عربي: متحكم لإدارة إعدادات حسابات الواتساب المتعددة للشركة أو العامة (عرض، إضافة، تعديل، حذف، تفعيل، تعيين افتراضي، واختبار الاتصال).

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Actions\SaveWhatsAppSettingAction;
use Modules\Notification\DTOs\WhatsAppSettingDTO;
use Modules\Notification\Http\Requests\WhatsAppSettingRequest;
use Modules\Notification\Http\Resources\WhatsAppSettingResource;
use Modules\Notification\Models\WhatsAppSetting;
use Modules\Notification\Services\WhatsAppService;

class WhatsAppSettingController extends Controller
{
    /**
     * عرض قائمة حسابات الواتساب المضافة للشركة الحالية أو العامة.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = WhatsAppSetting::get();
            return api_success(WhatsAppSettingResource::collection($settings), 'تم جلب قائمة حسابات الواتساب بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل حساب واتساب معين.
     */
    public function show($id): JsonResponse
    {
        try {
            $setting = WhatsAppSetting::findOrFail($id);
            return api_success(new WhatsAppSettingResource($setting), 'تم جلب تفاصيل حساب الواتساب بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة حساب واتساب جديد للشركة.
     */
    public function store(WhatsAppSettingRequest $request, SaveWhatsAppSettingAction $action): JsonResponse
    {
        try {
            $setting = $action->handle($request->validated());
            return api_success(new WhatsAppSettingResource($setting), 'تم إضافة حساب الواتساب بنجاح', 201);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعديل حساب واتساب حالي بالمعرف.
     */
    public function update(WhatsAppSettingRequest $request, $id, SaveWhatsAppSettingAction $action): JsonResponse
    {
        try {
            $setting = WhatsAppSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعديل السجلات العامة للسيستم.', 403);
            }

            $data = array_merge($request->validated(), ['id' => $id]);
            $setting = $action->handle($data);
            return api_success(new WhatsAppSettingResource($setting), 'تم تحديث حساب الواتساب بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف حساب واتساب معين.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $setting = WhatsAppSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بحذف السجلات العامة للسيستم.', 403);
            }

            // نمنع حذف الحساب الافتراضي إذا وجد غيره لحماية الإرسال بالخلفية
            if ($setting->is_default) {
                $hasOthers = WhatsAppSetting::where('company_id', Auth::user()->active_company_id)
                    ->where('id', '!=', $id)
                    ->exists();
                if ($hasOthers) {
                    return api_error('لا يمكن حذف الحساب الافتراضي مباشرة. يرجى تعيين حساب آخر كافتراضي أولاً.');
                }
            }

            $setting->delete();
            return api_success(null, 'تم حذف حساب الواتساب بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعيين حساب واتساب محدد كافتراضي للشركة.
     */
    public function setDefault($id): JsonResponse
    {
        try {
            $setting = WhatsAppSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعيين حساب سيستم عام كافتراضي مباشرة.', 403);
            }

            $companyId = Auth::user()->active_company_id;

            if (!$setting->is_active) {
                return api_error('لا يمكن تعيين حساب غير نشط كحساب افتراضي.');
            }

            $setting->update(['is_default' => true]);

            // إلغاء تعيين الافتراضي عن الحسابات الأخرى لنفس الشركة للواتساب
            WhatsAppSetting::where('company_id', $companyId)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            return api_success(new WhatsAppSettingResource($setting), 'تم تعيين الحساب كافتراضي بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * اختبار صحة إعدادات الواتساب وإرسال رسالة تجريبية.
     */
    public function testConnection(Request $request, $id): JsonResponse
    {
        $request->validate([
            'recipient' => 'required|string'
        ]);

        try {
            $setting = WhatsAppSetting::findOrFail($id);

            // بناء كائن خدمة الواتساب باستخدام إعدادات هذا الحساب
            $whatsappService = new WhatsAppService($setting);

            $message = "أهلاً بك! هذه الرسالة تم إرسالها تلقائياً لاختبار ربط إعدادات WhatsApp Cloud API الخاصة بشركتكم بنجاح على منصة HWNix ERP.";
            
            $result = $whatsappService->sendMessage($request->recipient, $message);

            if (!$result['success']) {
                return api_error('فشل إرسال الرسالة التجريبية: ' . ($result['error'] ?? 'خطأ غير معروف'));
            }

            return api_success(null, 'تم إرسال الرسالة التجريبية للواتساب بنجاح، يرجى التحقق من جوال المستلم.');
        } catch (\Throwable $e) {
            return api_error('فشل الاتصال أو الإرسال: ' . $e->getMessage());
        }
    }
}

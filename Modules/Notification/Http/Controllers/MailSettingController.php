<?php

namespace Modules\Notification\Http\Controllers;

// تعليق عربي: متحكم لإدارة قائمة حسابات البريد الإلكتروني المتعددة للشركة (إضافة، تعديل، حذف، تفعيل، تعيين افتراضي واختبار اتصال).

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Actions\SaveMailSettingAction;
use Modules\Notification\Http\Requests\MailSettingRequest;
use Modules\Notification\Http\Resources\MailSettingResource;
use Modules\Notification\Models\MailSetting;
use Modules\Notification\Services\DynamicMailer;

class MailSettingController extends Controller
{
    /**
     * عرض قائمة حسابات البريد الإلكتروني المضافة للشركة الحالية أو العامة.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = MailSetting::get();
            return api_success(MailSettingResource::collection($settings), 'تم جلب قائمة حسابات البريد بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل حساب بريد معين.
     */
    public function show($id): JsonResponse
    {
        try {
            $setting = MailSetting::findOrFail($id);
            return api_success(new MailSettingResource($setting), 'تم جلب تفاصيل حساب البريد بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة حساب بريد جديد للشركة.
     */
    public function store(MailSettingRequest $request, SaveMailSettingAction $action): JsonResponse
    {
        try {
            $setting = $action->handle($request->validated());
            return api_success(new MailSettingResource($setting), 'تم إضافة حساب البريد بنجاح', 201);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعديل حساب بريد حالي بالمعرف.
     */
    public function update(MailSettingRequest $request, $id, SaveMailSettingAction $action): JsonResponse
    {
        try {
            $setting = MailSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعديل السجلات العامة للسيستم.', 403);
            }

            $data = array_merge($request->validated(), ['id' => $id]);
            $setting = $action->handle($data);
            return api_success(new MailSettingResource($setting), 'تم تحديث حساب البريد بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف حساب بريد معين.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $setting = MailSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بحذف السجلات العامة للسيستم.', 403);
            }

            // نمنع حذف الحساب الافتراضي إذا وجد غيره، لحماية الإرسال بالخلفية
            if ($setting->is_default) {
                $hasOthers = MailSetting::where('company_id', Auth::user()->active_company_id)
                    ->where('id', '!=', $id)
                    ->exists();
                if ($hasOthers) {
                    return api_error('لا يمكن حذف الحساب الافتراضي مباشرة. يرجى تعيين حساب آخر كافتراضي أولاً.');
                }
            }

            $setting->delete();
            return api_success(null, 'تم حذف حساب البريد بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعيين حساب بريد محدد كافتراضي للشركة.
     */
    public function setDefault($id): JsonResponse
    {
        try {
            $setting = MailSetting::findOrFail($id);
            if ($setting->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعيين حساب سيستم عام كافتراضي مباشرة.', 403);
            }

            $companyId = Auth::user()->active_company_id;

            if (!$setting->is_active) {
                return api_error('لا يمكن تعيين حساب غير نشط كحساب افتراضي.');
            }

            $setting->update(['is_default' => true]);

            // إلغاء تعيين الافتراضي عن الحسابات الأخرى لنفس الشركة
            MailSetting::where('company_id', $companyId)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            return api_success(new MailSettingResource($setting), 'تم تعيين الحساب كافتراضي بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * اختبار صحة إعدادات خادم بريد محدد وإرسال رسالة تجريبية.
     */
    public function testConnection(Request $request, $id): JsonResponse
    {
        $request->validate([
            'recipient' => 'required|email'
        ]);

        try {
            $setting = MailSetting::findOrFail($id);

            // بناء الموزع الديناميكي واختبار الإرسال
            $mailer = DynamicMailer::getMailer($setting);

            $mailer->html('<h3>تهانينا!</h3><p>هذا البريد تم إرساله تلقائياً لاختبار إعدادات SMTP/Mailgun المحددة لشركتكم على منصة HWNix ERP.</p>', function ($message) use ($request, $setting) {
                $message->to($request->recipient)
                        ->subject('بريد تجريبي - اختبار الاتصال منصة HWNix ERP')
                        ->from($setting->mail_from_address, $setting->mail_from_name);
            });

            return api_success(null, 'تم إرسال البريد التجريبي بنجاح، يرجى التحقق من صندوق الوارد.');
        } catch (\Throwable $e) {
            return api_error('فشل الاتصال أو الإرسال: ' . $e->getMessage());
        }
    }
}

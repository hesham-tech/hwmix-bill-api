<?php

namespace Modules\Notification\Http\Controllers;

//   متحكم لإدارة قواعد أتمتة الإشعارات والخطوات المجدولة وتشغيلها يدوياً للشركة الحالية أو العامة مع عزل كامل.

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Actions\SaveNotificationWorkflowAction;
use Modules\Notification\Http\Requests\NotificationWorkflowRequest;
use Modules\Notification\Http\Resources\NotificationWorkflowResource;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\WorkflowProcessor;
use Modules\Sales\Models\Invoice;
use Carbon\Carbon;

class NotificationWorkflowController extends Controller
{
    /**
     * عرض قائمة القواعد المضافة للشركة الحالية أو العامة.
     */
    public function index(): JsonResponse
    {
        try {
            $workflows = NotificationWorkflow::with('steps.template')->get();
            return api_success(NotificationWorkflowResource::collection($workflows), 'تم جلب قواعد الإشعارات بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل قاعدة أتمتة معينة.
     */
    public function show($id): JsonResponse
    {
        try {
            $workflow = NotificationWorkflow::with('steps.template')->findOrFail($id);
            return api_success(new NotificationWorkflowResource($workflow), 'تم جلب تفاصيل القاعدة بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قاعدة أتمتة إشعارات جديدة للشركة مع خطواتها.
     */
    public function store(NotificationWorkflowRequest $request, SaveNotificationWorkflowAction $action): JsonResponse
    {
        try {
            $workflow = $action->handle($request->validated());
            return api_success(new NotificationWorkflowResource($workflow), 'تم إضافة قاعدة الإشعار بنجاح', 201);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعديل قاعدة أتمتة وخطواتها بالمعرف.
     */
    public function update(NotificationWorkflowRequest $request, $id, SaveNotificationWorkflowAction $action): JsonResponse
    {
        try {
            $workflow = NotificationWorkflow::findOrFail($id);
            if ($workflow->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعديل السجلات العامة للسيستم.', 403);
            }

            $data = array_merge($request->validated(), ['id' => $id]);
            $workflow = $action->handle($data);
            return api_success(new NotificationWorkflowResource($workflow), 'تم تحديث قاعدة الإشعار بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قاعدة أتمتة إشعارات معينة.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $workflow = NotificationWorkflow::findOrFail($id);
            if ($workflow->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بحذف السجلات العامة للسيستم.', 403);
            }

            $workflow->delete();
            return api_success(null, 'تم حذف قاعدة الإشعار بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل الفحص التلقائي يدوياً وفوراً لهذه القاعدة الآن.
     */
    public function runNow($id): JsonResponse
    {
        try {
            $companyId = Auth::user()->active_company_id;
            $workflow = NotificationWorkflow::with('steps.template')->findOrFail($id);

            if (!$workflow->is_active) {
                return api_error('لا يمكن تشغيل قاعدة أتمتة غير نشطة.');
            }

            $processor = app(WorkflowProcessor::class);
            $processedCount = 0;

            if (in_array($workflow->event_type, ['invoice.due_soon', 'invoice.overdue'])) {
                foreach ($workflow->steps as $step) {
                    if (!$step->is_active || !$step->template)
                        continue;

                    // حساب تاريخ الاستحقاق المستهدف بناءً على الإزاحة delay_days
                    $targetDate = Carbon::today()->subDays($step->delay_days)->toDateString();

                    // جلب الفواتير غير المدفوعة للشركة في تاريخ الاستحقاق المستهدف
                    $invoices = Invoice::where('company_id', $companyId)
                        ->whereDate('due_date', $targetDate)
                        ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                        ->get();

                    foreach ($invoices as $invoice) {
                        $processor->executeStep($step, $invoice);
                        $processedCount++;
                    }
                }
            } else {
                return api_error('تشغيل الآن مدعوم فقط لقواعد الجدولة الزمنية مثل الفواتير المتأخرة أو الفواتير المستحقة قريباً.');
            }

            return api_success(
                ['processed_count' => $processedCount],
                "تم تشغيل الفحص بنجاح، وتمت معالجة وإرسال التنبيهات لـ {$processedCount} مستند/عميل تنطبق عليه الشروط."
            );
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * التحقق من حالة توفر قنوات الاتصال والربط النشطة للشركة الحالية.
     */
    public function integrationsStatus(): JsonResponse
    {
        try {
            $hasEmail = \Modules\Notification\Models\MailSetting::where('is_active', true)->exists();
            $hasWhatsApp = \Modules\Notification\Models\WhatsAppSetting::where('is_active', true)->exists();

            return api_success([
                'email' => $hasEmail,
                'whatsapp' => $hasWhatsApp
            ], 'تم جلب حالة توفر الخدمات بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}

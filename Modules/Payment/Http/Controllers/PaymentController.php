<?php

namespace Modules\Payment\Http\Controllers;

// تعليق عربي: متحكم عمليات الدفع الإلكتروني واستقبال تنبيهات الـ Webhooks لتحديث حالة الدفع تلقائياً.

use App\Http\Controllers\Controller;
use Modules\Payment\Http\Requests\ProcessPaymentRequest;
use Modules\Payment\Actions\ProcessPaymentAction;
use Modules\Payment\Actions\HandleWebhookAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    /**
     * بدء عملية الدفع وإنشاء رابط المعاملة
     */
    public function process(ProcessPaymentRequest $request, ProcessPaymentAction $action): JsonResponse
    {
        try {
            $result = $action->handle($request->validated());
            return api_success($result, 'تم بدء عملية الدفع الإلكتروني بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * معالجة الـ Webhook الوارد من بوابة الدفع وتعديل الحالة تلقائياً
     */
    public function webhook(Request $request, string $driver, HandleWebhookAction $action): JsonResponse
    {
        try {
            $result = $action->handle([
                'driver' => $driver,
                'payload' => $request->all(),
            ]);

            if (!$result['success']) {
                return api_error($result['error'] ?? 'فشل معالجة التنبيه.', 400);
            }

            return api_success($result, 'تمت معالجة التنبيه وتحديث حالة الدفعة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * رابط الكولباك بعد عودة المستخدم من صفحة الدفع (صفحة التوجيه والعودة للفرونت إند)
     */
    public function callback(Request $request, string $driver): JsonResponse
    {
        try {
            $status = $request->get('status', 'success');
            return api_success([
                'driver' => $driver,
                'status' => $status,
                'session_id' => $request->get('session_id'),
                'order_id' => $request->get('order'),
            ], 'تمت العودة من بوابة الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

<?php

namespace Modules\Payment\Http\Controllers;

// تعليق عربي: متحكم إدارة بوابات الدفع الإلكتروني المخصصة للشركة لتمكين إدارة الإعدادات والربط.

use App\Http\Controllers\Controller;
use Modules\Payment\Http\Requests\PaymentGatewayRequest;
use Modules\Payment\Http\Resources\PaymentGatewayResource;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\DTOs\PaymentGatewayDTO;
use Modules\Payment\Actions\SavePaymentGatewayAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentGatewayController extends Controller
{
    /**
     * عرض بوابات الدفع النشطة للشركة الحالية
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $gateways = PaymentGateway::all();
            return api_success(PaymentGatewayResource::collection($gateways), 'تم استرداد بوابات الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حفظ إعدادات بوابة دفع جديدة
     */
    public function store(PaymentGatewayRequest $request, SavePaymentGatewayAction $action): JsonResponse
    {
        try {
            $dto = PaymentGatewayDTO::fromRequest($request->validated());
            $gateway = $action->handle(['dto' => $dto]);
            return api_success(new PaymentGatewayResource($gateway), 'تم حفظ بوابة الدفع بنجاح.', 201);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل بوابة دفع معينة
     */
    public function show($id): JsonResponse
    {
        try {
            $gateway = PaymentGateway::findOrFail($id);
            return api_success(new PaymentGatewayResource($gateway), 'تم استرداد بيانات بوابة الدفع.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث إعدادات بوابة دفع
     */
    public function update(PaymentGatewayRequest $request, $id, SavePaymentGatewayAction $action): JsonResponse
    {
        try {
            $dto = PaymentGatewayDTO::fromRequest($request->validated());
            $gateway = $action->handle(['dto' => $dto, 'id' => $id]);
            return api_success(new PaymentGatewayResource($gateway), 'تم تحديث بوابة الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف بوابة الدفع (Soft Delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $gateway = PaymentGateway::findOrFail($id);
            $gateway->delete();
            return api_success(null, 'تم حذف بوابة الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعيين بوابة الدفع كافتراضية وإلغاء البقية للشركة
     */
    public function setDefault($id): JsonResponse
    {
        try {
            $gateway = PaymentGateway::findOrFail($id);
            
            \Illuminate\Support\Facades\DB::transaction(function() use ($gateway) {
                // إلغاء الافتراضية للبوابات الأخرى لنفس الشركة
                PaymentGateway::where('company_id', $gateway->company_id)
                    ->where('id', '!=', $gateway->id)
                    ->update(['is_default' => false]);
                
                // تعيين البوابة الحالية كافتراضية
                $gateway->update(['is_default' => true]);
            });

            return api_success(new PaymentGatewayResource($gateway), 'تم تعيين بوابة الدفع كبوابة افتراضية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

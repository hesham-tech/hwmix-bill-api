<?php

namespace App\Http\Controllers\SaaS;

// تعليق عربي: متحكم لإدارة تسعيرة اشتراكات الساس وحساب تكلفة الباقات بناءً على الشهور والكوبونات المدخلة.

use App\Http\Controllers\Controller;
use App\Http\Requests\SaaS\CalculatePricingRequest;
use App\Http\Requests\SaaS\ValidateCouponRequest;
use App\Services\SaaS\PricingCalculator;
use Illuminate\Http\JsonResponse;

class SaaSPricingController extends Controller
{
    /**
     * حساب تكلفة الاشتراك بالباقة مع تطبيق الخصومات والكوبون.
     */
    public function calculate(CalculatePricingRequest $request): JsonResponse
    {
        try {
            $planId = (int) $request->input('plan_id');
            $months = (int) $request->input('months');
            $couponCode = $request->input('coupon_code');

            $pricing = PricingCalculator::calculate($planId, $months, $couponCode);

            return api_success($pricing, 'تم حساب تفاصيل التسعيرة بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * التحقق من كود الخصم (الكوبون) وحساب قيمته التقديرية.
     */
    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        try {
            $planId = (int) $request->input('plan_id');
            $months = (int) $request->input('months');
            $couponCode = $request->input('coupon_code');

            $pricing = PricingCalculator::calculate($planId, $months, $couponCode);

            if (!empty($pricing['coupon_error'])) {
                return api_error($pricing['coupon_error'], [
                    'coupon_code' => $couponCode
                ], 422);
            }

            return api_success($pricing['coupon'], 'كود الخصم فعال وصالح للاستخدام.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}

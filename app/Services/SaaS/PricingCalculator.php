<?php

namespace App\Services\SaaS;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// تعليق عربي: محرك حساب وتسعير اشتراكات SaaS المتقدم، يقوم باحتساب تكلفة الاشتراك وخصومات الشرائح التدريجية وتطبيق أكواد الخصم والكوبونات.
class PricingCalculator
{
    /**
     * حساب تكلفة الاشتراك بالتفصيل وتطبيق خصومات الشرائح والكوبون.
     */
    public static function calculate(int $planId, int $months, ?string $couponCode = null): array
    {
        $plan = Plan::findOrFail($planId);
        $basePricePerMonth = (float) $plan->price; // سعر الشهر الأساسي
        $subtotal = $basePricePerMonth * $months;

        // 1. فحص شريحة الخصم المناسبة للمدة المحددة (Tiered Pricing)
        $tierPricePerMonth = $basePricePerMonth;
        
        $tier = DB::table('plan_pricing_tiers')
            ->where('plan_id', $planId)
            ->where('min_months', '<=', $months)
            ->where(function ($query) use ($months) {
                $query->whereNull('max_months')
                      ->orWhere('max_months', '>=', $months);
            })
            ->first();

        if ($tier) {
            if ((float) $tier->price_per_month > 0) {
                $tierPricePerMonth = (float) $tier->price_per_month;
            } elseif ((float) $tier->discount_percent > 0) {
                $tierPricePerMonth = $basePricePerMonth * (1 - ((float) $tier->discount_percent / 100));
            }
        }
        
        $tieredPrice = $tierPricePerMonth * $months;
        $tieredDiscountAmount = $subtotal - $tieredPrice;

        // 2. تطبيق الكوبون إن وجد وتم التحقق منه
        $couponDiscountAmount = 0.00;
        $couponError = null;
        $coupon = null;

        if ($couponCode) {
            $coupon = DB::table('coupons')
                ->where('code', $couponCode)
                ->where('status', true)
                ->where('starts_at', '<=', Carbon::now())
                ->where('ends_at', '>=', Carbon::now())
                ->whereNull('deleted_at')
                ->first();

            if ($coupon) {
                // التحقق من صلاحية الاستخدام الأقصى
                if ($coupon->max_uses > 0 && $coupon->used_count >= $coupon->max_uses) {
                    $couponError = 'لقد تجاوز هذا الكوبون الحد الأقصى للاستخدام.';
                    $coupon = null;
                }
            } else {
                $couponError = 'كود الخصم غير صحيح أو منتهي الصلاحية.';
            }

            if ($coupon) {
                // حساب خصم الكوبون على السعر المخفض للشرائح (Compound Discount)
                if ($coupon->discount_type === 'percent') {
                    $couponDiscountAmount = $tieredPrice * ((float) $coupon->value / 100);
                } else { // قيمة ثابتة
                    $couponDiscountAmount = min((float) $coupon->value, $tieredPrice);
                }
            }
        }

        // حساب الإجماليات بدقة decimal(18,2)
        $totalPrice = max(0.00, $tieredPrice - $couponDiscountAmount);
        $totalDiscountAmount = $subtotal - $totalPrice;
        $savings = $subtotal - $totalPrice;

        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'months' => $months,
            'base_price_per_month' => round($basePricePerMonth, 2),
            'tiered_price_per_month' => round($tierPricePerMonth, 2),
            'effective_price_per_month' => $months > 0 ? round($totalPrice / $months, 2) : 0.00,
            
            // تفاصيل المبلغ والفروقات
            'subtotal' => round($subtotal, 2),
            'tiered_discount_amount' => round($tieredDiscountAmount, 2),
            'coupon_discount_amount' => round($couponDiscountAmount, 2),
            'total_discount_amount' => round($totalDiscountAmount, 2),
            'total_price' => round($totalPrice, 2),
            'savings' => round($savings, 2),
            
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'value' => (float) $coupon->value,
            ] : null,
            'coupon_error' => $couponError,
        ];
    }
}

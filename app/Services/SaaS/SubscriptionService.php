<?php

namespace App\Services\SaaS;

//   خدمة إدارة اشتراكات الساس (SaaS Subscriptions) للشركات لتسجيل الباقة، حساب التواريخ وفترة التجربة والترقيات.

use App\Models\Plan;
use App\Models\CompanySubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SubscriptionService
{
    /**
     * تفعيل واشتراك باقة جديدة لشركة مع خيار تحديد الأشهر والكوبون.
     */
    public static function initializeSubscription(int $companyId, int $planId, int $months = 1, ?string $couponCode = null, bool $skipTrial = false): CompanySubscription
    {
        $plan = Plan::findOrFail($planId);

        // 1. حساب السعر والخصومات والكوبونات بدقة عبرPricingCalculator
        $pricing = PricingCalculator::calculate($planId, $months, $couponCode);
        $totalPrice = $pricing['total_price'] ?: 0.00;

        $startsAt = Carbon::now();
        $trialDays = $skipTrial ? 0 : ($plan->trial_days ?: 0);
        $trialEndsAt = $trialDays > 0 ? Carbon::now()->addDays($trialDays) : null;
        $status = $trialDays > 0 ? 'trial' : ($totalPrice > 0 ? 'pending' : 'active');

        // حساب تاريخ انتهاء الاشتراك بناء على الأشهر المختارة
        $endsAt = Carbon::now()->addMonths($months);

        // في حال وجود تجربة، يبدأ وقت انتهاء الاشتراك الفعلي بعد انتهاء التجربة
        if ($trialEndsAt) {
            $endsAt->addDays($trialDays);
        }

        // إلغاء أي اشتراكات سابقة نشطة للشركة
        CompanySubscription::where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'canceled']);

        $subscription = CompanySubscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'price' => $totalPrice,
            'months' => $months,
            'coupon_code' => $pricing['coupon'] ? $pricing['coupon']['code'] : null,
            'billing_cycle' => $plan->type ?: 'monthly',
            'status' => $status,
            'max_users' => $plan->max_users,
            'max_products' => $plan->max_products,
            'max_invoices' => $plan->max_invoices,
            'features' => $plan->features,
            'created_by' => Auth::id() ?: $plan->created_by ?: 1,
        ]);

        // إذا تم تفعيل الاشتراك مباشرة بدون دفع معلق (اشتراك مجاني مثلاً) وكان يملك كوبون خصم نشط، نقوم بزيادة عدد مرات استخدامه
        if ($status === 'active' && $pricing['coupon']) {
            \Illuminate\Support\Facades\DB::table('coupons')
                ->where('code', $pricing['coupon']['code'])
                ->increment('used_count');
        }

        return $subscription;
    }

    /**
     * ترقية الباقة لشركة حالية مع تحديد الأشهر والكوبون.
     */
    public static function upgradePlan(int $companyId, int $newPlanId, int $months = 1, ?string $couponCode = null, bool $skipTrial = true): CompanySubscription
    {
        // نقوم بإنشاء اشتراك جديد بالباقة الجديدة تلقائياً مع خيار تخطي التجربة
        return self::initializeSubscription($companyId, $newPlanId, $months, $couponCode, $skipTrial);
    }

    /**
     * تهيئة اشتراك معلق الدفع لترقية أو تجديد الباقة بدعم ديناميكي للأشهر والكوبون.
     */
    public static function initializePendingSubscription(int $companyId, int $planId, int $months = 1, ?string $couponCode = null): CompanySubscription
    {
        $plan = Plan::findOrFail($planId);

        // حساب السعر والكوبونات بدقة للطلب المعلق
        $pricing = PricingCalculator::calculate($planId, $months, $couponCode);
        $totalPrice = $pricing['total_price'] ?: 0.00;

        $startsAt = Carbon::now();
        $endsAt = Carbon::now()->addMonths($months);

        // ننشئ الاشتراك بحالة pending دون إلغاء الاشتراكات النشطة الحالية
        return CompanySubscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => null, // المعلق لا يملك فترة تجربة
            'price' => $totalPrice,
            'months' => $months,
            'coupon_code' => $pricing['coupon'] ? $pricing['coupon']['code'] : null,
            'billing_cycle' => $plan->type ?: 'monthly',
            'status' => 'pending',
            'max_users' => $plan->max_users,
            'max_products' => $plan->max_products,
            'max_invoices' => $plan->max_invoices,
            'features' => $plan->features,
            'created_by' => Auth::id() ?: $plan->created_by ?: 1,
        ]);
    }
}

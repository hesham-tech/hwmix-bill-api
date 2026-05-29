<?php

namespace App\Services\SaaS;

// تعليق عربي: خدمة إدارة اشتراكات الساس (SaaS Subscriptions) للشركات لتسجيل الباقة، حساب التواريخ وفترة التجربة والترقيات.

use App\Models\Plan;
use App\Models\CompanySubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SubscriptionService
{
    /**
     * تفعيل واشتراك باقة جديدة لشركة.
     */
    public static function initializeSubscription(int $companyId, int $planId): CompanySubscription
    {
        $plan = Plan::findOrFail($planId);
        
        $startsAt = Carbon::now();
        $trialDays = $plan->trial_days ?: 0;
        $trialEndsAt = $trialDays > 0 ? Carbon::now()->addDays($trialDays) : null;
        $status = $trialDays > 0 ? 'trial' : 'active';
        
        // حساب تاريخ انتهاء الاشتراك بناء على مدة الباقة
        $endsAt = null;
        if ($plan->duration && $plan->duration_unit) {
            $unit = strtolower($plan->duration_unit);
            $endsAt = Carbon::now();
            if ($unit === 'day' || $unit === 'days') {
                $endsAt->addDays($plan->duration);
            } elseif ($unit === 'month' || $unit === 'months') {
                $endsAt->addMonths($plan->duration);
            } elseif ($unit === 'year' || $unit === 'years') {
                $endsAt->addYears($plan->duration);
            } else {
                $endsAt->addMonths($plan->duration); // افتراضي أشهر
            }
            
            // في حال وجود تجربة، يبدأ وقت انتهاء الاشتراك الفعلي بعد انتهاء التجربة
            if ($trialEndsAt) {
                $endsAt->addDays($trialDays);
            }
        }

        // إلغاء أي اشتراكات سابقة نشطة للشركة
        CompanySubscription::where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'canceled']);

        return CompanySubscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'price' => $plan->price ?: 0,
            'billing_cycle' => $plan->type ?: 'monthly',
            'status' => $status,
            'created_by' => Auth::id() ?: $plan->created_by,
        ]);
    }

    /**
     * ترقية الباقة لشركة حالية.
     */
    public static function upgradePlan(int $companyId, int $newPlanId): CompanySubscription
    {
        // نقوم بإنشاء اشتراك جديد بالباقة الجديدة تلقائياً
        return self::initializeSubscription($companyId, $newPlanId);
    }
}

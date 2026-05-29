<?php

namespace App\Services\SaaS;

// تعليق عربي: خدمة التحقق من صلاحيات الباقة وميزات النظام المتاحة للشركة المشتركة بالمنصة (مثل بوابات الدفع، التقارير المتقدمة وغيرها).

use App\Models\CompanySubscription;

class FeatureAccessService
{
    /**
     * التحقق هل الباقة الحالية للشركة تدعم ميزة معينة.
     */
    public static function hasAccess(int $companyId, string $featureKey): bool
    {
        // 1. الشركة الأم (Master Company / SaaS Admin) معفاة تماماً ولديها إمكانية الوصول لكافة الميزات
        $masterCompanyId = (int) config('app.master_company_id', 1);
        if ($companyId === $masterCompanyId) {
            return true;
        }

        // 2. جلب الاشتراك الفعال للشركة
        $subscription = CompanySubscription::with('plan')
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->get()
            ->filter(function($sub) {
                return $sub->isActive();
            })
            ->first();

        if (!$subscription) {
            return false; // لا يوجد اشتراك نشط -> لا يوجد وصول لأي ميزة إضافية
        }

        // 3. جلب الميزات المتاحة (الأولوية لتخصيص ميزات الاشتراك، ثم ميزات الباقة الافتراضية)
        $features = $subscription->features ?? $subscription->plan?->features ?? [];

        if (is_string($features)) {
            $features = json_decode($features, true);
        }

        if (!is_array($features)) {
            return false;
        }

        // التحقق من تفعيل الميزة
        return isset($features[$featureKey]) && (bool) $features[$featureKey];
    }
}

<?php

namespace App\Services\SaaS;

use App\Models\CompanySubscription;
use App\Models\Plan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// تعليق عربي: المحرك المركزي الموحد لإدارة وتطبيق شروط الاشتراك ديناميكياً والتحقق من الميزات والحدود النشطة.
class SaaSEngine
{
    /**
     * التحقق من تفعيل ميزة (Feature Flag) معينة للشركة.
     */
    public static function hasFeature(int $companyId, string $featureKey): bool
    {
        // 1. الشركة الأم معفاة تماماً
        $masterCompanyId = (int) config('app.master_company_id', 1);
        if ($companyId === $masterCompanyId) {
            return true;
        }

        // 2. جلب الاشتراك الفعال
        $subscription = self::getActiveSubscription($companyId);
        if (!$subscription) {
            return false;
        }

        // 3. جلب الميزات المحددة للعميل
        $features = $subscription->features ?? $subscription->plan?->features ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }

        // دعم الهيكل الهرمي: flags.feature_key أو المباشر feature_key
        return (bool) ($features['flags'][$featureKey] ?? $features[$featureKey] ?? false);
    }

    /**
     * التحقق هل استهلاك مورد معين للشركة ما زال في الحدود المسموح بها.
     */
    public static function isWithinLimit(int $companyId, string $resource): bool
    {
        // 1. الشركة الأم معفاة تماماً
        $masterCompanyId = (int) config('app.master_company_id', 1);
        if ($companyId === $masterCompanyId) {
            return true;
        }

        // 2. جلب الاشتراك الفعال
        $subscription = self::getActiveSubscription($companyId);
        if (!$subscription) {
            return false;
        }

        // 3. الحصول على الحد الأقصى ديناميكياً
        $limit = self::resolveMaxLimit($subscription, $resource);

        // إذا كان الحد غير محدد أو -1 فهو غير محدود
        if ($limit === null || (int) $limit === -1) {
            return true;
        }

        // 4. الحصول على الاستهلاك الفعلي عبر الكاش
        $currentUsage = CachedUsageCounter::get($companyId, $resource);

        return $currentUsage < (int) $limit;
    }

    /**
     * استرجاع مصفوفة الاستهلاك الكاملة للشركة لجميع الموارد المسجلة.
     */
    public static function getSubscriptionUsageMatrix(int $companyId): array
    {
        $subscription = self::getActiveSubscription($companyId);

        $matrix = [
            'plan_id' => $subscription ? $subscription->plan_id : null,
            'plan_name' => $subscription ? ($subscription->plan?->name ?? 'باقة مخصصة') : 'بدون باقة نشطة',
            'status' => $subscription ? $subscription->status : 'inactive',
            'auto_renew' => $subscription ? (bool) $subscription->auto_renew : true,
            'starts_at' => $subscription ? $subscription->starts_at->format('Y-m-d') : null,
            'ends_at' => $subscription ? ($subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : 'لا ينتهي') : null,
            'trial_ends_at' => $subscription ? ($subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null) : null,
            'limits' => []
        ];

        // جلب قائمة الموارد المعرفة في قاعدة البيانات ديناميكياً
        $resources = [];
        try {
            if (Schema::hasTable('usage_metrics')) {
                $resources = DB::table('usage_metrics')
                    ->where('status', true)
                    ->pluck('key')
                    ->toArray();
            }
        } catch (\Throwable $e) {}

        // في حال عدم وجود موارد في قاعدة البيانات (حالة الفولباك)
        if (empty($resources)) {
            $resources = ['users', 'products', 'invoices', 'warehouses', 'whatsapp_messages', 'api_calls', 'storage_size'];
        }

        foreach ($resources as $resource) {
            $limit = $subscription ? self::resolveMaxLimit($subscription, $resource) : null;
            $current = CachedUsageCounter::get($companyId, $resource);
            
            $isUnlimited = ($limit === null || (int) $limit === -1);
            $maxVal = $isUnlimited ? 'غير محدود' : (int) $limit;

            $matrix['limits'][$resource] = [
                'current' => $current,
                'max' => $maxVal,
                'is_unlimited' => $isUnlimited,
                'percent' => ($isUnlimited || (int) $limit === 0) ? 0 : round(($current / (int) $limit) * 100, 1),
            ];
        }

        return $matrix;
    }

    /**
     * جلب قيمة الحد الأقصى للمورد ديناميكياً من الاشتراك أو الباقة.
     */
    protected static function resolveMaxLimit(CompanySubscription $subscription, string $resource): ?int
    {
        $columnName = "max_{$resource}";
        if ($resource === 'storage_size') {
            $columnName = 'max_storage_mb';
        }

        // 1. فحص العمود المباشر في كائن الاشتراك
        $limit = $subscription->$columnName ?? null;

        // 2. فحص الحقل في مصفوفة JSON للاشتراك
        if ($limit === null) {
            $subFeatures = $subscription->features ?: [];
            if (is_string($subFeatures)) {
                $subFeatures = json_decode($subFeatures, true) ?: [];
            }
            $limit = $subFeatures['limits'][$resource] 
                ?? $subFeatures[$resource] 
                ?? $subFeatures["max_{$resource}"]
                ?? null;
        }

        // 3. فحص الباقة الافتراضية
        if ($limit === null && $subscription->plan) {
            $limit = $subscription->plan->$columnName ?? null;

            if ($limit === null) {
                $planFeatures = $subscription->plan->features ?: [];
                if (is_string($planFeatures)) {
                    $planFeatures = json_decode($planFeatures, true) ?: [];
                }
                $limit = $planFeatures['limits'][$resource] 
                    ?? $planFeatures[$resource] 
                    ?? $planFeatures["max_{$resource}"]
                    ?? null;
            }
        }

        return $limit !== null ? (int) $limit : null;
    }

    /**
     * الحصول على الاشتراك الفعال للشركة مع الدعم الذاتي التلقائي.
     */
    public static function getActiveSubscription(int $companyId): ?CompanySubscription
    {
        $subscription = CompanySubscription::with('plan')
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->get()
            ->filter(fn($sub) => $sub->isActive())
            ->first();

        // علاج تلقائي ذاتي للباقة التجريبية في حال عدم وجود أي اشتراكات سابقة للشركة
        if (!$subscription) {
            $hasAnySubscription = CompanySubscription::where('company_id', $companyId)->exists();
            if (!$hasAnySubscription) {
                $freePlan = Plan::where('code', 'free_trial')->first();
                if ($freePlan) {
                    try {
                        $subscription = SubscriptionService::initializeSubscription($companyId, $freePlan->id);
                    } catch (\Throwable $e) {
                        Log::error('SaaS self-healing in SaaSEngine failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        return $subscription;
    }
}

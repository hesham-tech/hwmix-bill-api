<?php

namespace App\Services\SaaS;

// تعليق عربي: خدمة حساب الاستهلاك الفعلي والتحقق من قيود الباقات النشطة للشركات المشتركة بالمنصة لضمان عدم تجاوز حدود الاستخدام.

use App\Models\CompanySubscription;
use App\Models\CompanyUser;
use App\Models\Invoice;
use Modules\Inventory\Models\Product;
use Modules\Companies\Models\Branch; // للتحقق من الفروع إن أردنا
use Illuminate\Support\Facades\Schema;

class LimitResolver
{
    /**
     * حساب الاستهلاك الحالي لمورد معين للشركة.
     */
    public static function getCurrentUsage(int $companyId, string $resource): int
    {
        switch ($resource) {
            case 'users':
                return CompanyUser::where('company_id', $companyId)->count();
                
            case 'products':
                return Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->count();
                
            case 'invoices':
                return Invoice::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->count();
                
            case 'warehouses':
                // المخازن موجودة في موديول المخازن
                return \Modules\Inventory\Models\Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->count();
                    
            default:
                return 0;
        }
    }

    /**
     * التحقق هل استهلاك الشركة الحالي ضمن المسموح به للباقة النشطة.
     */
    public static function isWithinLimit(int $companyId, string $resource): bool
    {
        // 1. الشركة الأم (Master Company / SaaS Admin) معفاة تماماً ولديها حدود لا نهائية
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
            // تفعيل تلقائي ذاتي للباقة التجريبية الافتراضية لمنع توقف الشركات القديمة أو المهيأة يدوياً
            $freePlan = \App\Models\Plan::where('code', 'free_trial')->first();
            if ($freePlan) {
                try {
                    $subscription = \App\Services\SaaS\SubscriptionService::initializeSubscription($companyId, $freePlan->id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('SaaS self-healing failed to initialize subscription', ['company_id' => $companyId, 'error' => $e->getMessage()]);
                }
            }
        }

        if (!$subscription) {
            return false; // لا يوجد اشتراك نشط -> لا يمكن إنشاء موارد
        }

        // 3. جلب القيد الأقصى المحدد (الأولوية لتخصيص الاشتراك الاستثنائي، ثم ميزات الاشتراك JSON، ثم قيود الباقة الافتراضية)
        $limit = null;
        
        // فك تشفير ميزات الباقة واشتراك العميل
        $subFeatures = $subscription->features ?: [];
        if (is_string($subFeatures)) {
            $subFeatures = json_decode($subFeatures, true) ?: [];
        }
        
        $planFeatures = $subscription->plan?->features ?: [];
        if (is_string($planFeatures)) {
            $planFeatures = json_decode($planFeatures, true) ?: [];
        }

        if ($resource === 'users') {
            $limit = $subscription->max_users 
                ?? $subFeatures['max_users'] 
                ?? $subscription->plan?->max_users 
                ?? $planFeatures['max_users'] 
                ?? null;
        } elseif ($resource === 'products') {
            $limit = $subscription->max_products 
                ?? $subFeatures['max_products'] 
                ?? $subscription->plan?->max_products 
                ?? $planFeatures['max_products'] 
                ?? null;
        } elseif ($resource === 'invoices') {
            $limit = $subscription->max_invoices 
                ?? $subFeatures['max_invoices'] 
                ?? $subscription->plan?->max_invoices 
                ?? $planFeatures['max_invoices'] 
                ?? null;
        } elseif ($resource === 'warehouses') {
            $limit = $subFeatures['max_warehouses'] 
                ?? $planFeatures['max_warehouses'] 
                ?? null;
        }

        // إذا كان القيد خالي أو يساوي -1 نعتبره غير محدود
        if ($limit === null || (int)$limit === -1) {
            return true;
        }

        $currentUsage = self::getCurrentUsage($companyId, $resource);

        return $currentUsage < (int)$limit;
    }

    /**
     * استرجاع مصفوفة إحصائية متكاملة لجميع الاستهلاكات والحدود للشركة.
     */
    public static function getSubscriptionUsageMatrix(int $companyId): array
    {
        $subscription = CompanySubscription::with('plan')
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->get()
            ->filter(function($sub) {
                return $sub->isActive();
            })
            ->first();

        if (!$subscription) {
            // تفعيل تلقائي ذاتي للباقة التجريبية الافتراضية للشركات القديمة عند الاستعلام عن المصفوفة
            $freePlan = \App\Models\Plan::where('code', 'free_trial')->first();
            if ($freePlan) {
                try {
                    $subscription = \App\Services\SaaS\SubscriptionService::initializeSubscription($companyId, $freePlan->id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('SaaS matrix self-healing failed to initialize subscription', ['company_id' => $companyId, 'error' => $e->getMessage()]);
                }
            }
        }

        $matrix = [
            'plan_id' => $subscription ? $subscription->plan_id : null,
            'plan_name' => $subscription ? ($subscription->plan?->name ?? 'باقة مخصصة') : 'بدون باقة نشطة',
            'status' => $subscription ? $subscription->status : 'inactive',
            'auto_renew' => $subscription ? (bool)$subscription->auto_renew : true,
            'starts_at' => $subscription ? $subscription->starts_at->format('Y-m-d') : null,
            'ends_at' => $subscription ? ($subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : 'لا ينتهي') : null,
            'trial_ends_at' => $subscription ? ($subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null) : null,
            'limits' => []
        ];

        $resources = ['users', 'products', 'invoices', 'warehouses'];
        
        $subFeatures = [];
        $planFeatures = [];
        if ($subscription) {
            $subFeatures = $subscription->features ?: [];
            if (is_string($subFeatures)) {
                $subFeatures = json_decode($subFeatures, true) ?: [];
            }
            $planFeatures = $subscription->plan?->features ?: [];
            if (is_string($planFeatures)) {
                $planFeatures = json_decode($planFeatures, true) ?: [];
            }
        }

        foreach ($resources as $resource) {
            $limit = null;
            if ($subscription) {
                if ($resource === 'users') {
                    $limit = $subscription->max_users ?? $subFeatures['max_users'] ?? $subscription->plan?->max_users ?? $planFeatures['max_users'] ?? null;
                } elseif ($resource === 'products') {
                    $limit = $subscription->max_products ?? $subFeatures['max_products'] ?? $subscription->plan?->max_products ?? $planFeatures['max_products'] ?? null;
                } elseif ($resource === 'invoices') {
                    $limit = $subscription->max_invoices ?? $subFeatures['max_invoices'] ?? $subscription->plan?->max_invoices ?? $planFeatures['max_invoices'] ?? null;
                } elseif ($resource === 'warehouses') {
                    $limit = $subFeatures['max_warehouses'] ?? $planFeatures['max_warehouses'] ?? null;
                }
            }

            $current = self::getCurrentUsage($companyId, $resource);

            $matrix['limits'][$resource] = [
                'current' => $current,
                'max' => ($limit === null || (int)$limit === -1) ? 'غير محدود' : (int) $limit,
                'is_unlimited' => ($limit === null || (int)$limit === -1),
                'percent' => ($limit === null || (int)$limit === -1 || (int)$limit === 0) ? 0 : round(($current / (int)$limit) * 100, 1),
            ];
        }

        return $matrix;
    }
}

<?php

namespace App\Services\SaaS;

use Illuminate\Support\Facades\Cache;

//   كلاس لإدارة الكاش السريع لاستهلاك الموارد وتحديثه تلقائياً لتقليل استهلاك موارد قاعدة البيانات.
class CachedUsageCounter
{
    protected static int $cacheTtl = 300; // 5 دقائق كاش افتراضي

    /**
     * الحصول على استهلاك مورد معين من الكاش أو حسابه وتخزينه.
     */
    public static function get(int $companyId, string $key): int
    {
        $cacheKey = self::getCacheKey($companyId, $key);

        return Cache::remember($cacheKey, self::$cacheTtl, function () use ($companyId, $key) {
            $driver = UsageDriverRegistry::getDriver($key);
            if ($driver) {
                return $driver->resolve($companyId);
            }
            return 0;
        });
    }

    /**
     * تصفير كاش مورد معين للشركة عند حدوث تعديل (إضافة/حذف).
     */
    public static function clear(int $companyId, string $key): void
    {
        $cacheKey = self::getCacheKey($companyId, $key);
        Cache::forget($cacheKey);
    }

    /**
     * تصفير كاش كافة الموارد للشركة.
     */
    public static function clearAll(int $companyId): void
    {
        $keys = ['users', 'products', 'invoices', 'warehouses', 'whatsapp_messages', 'api_calls', 'storage_size'];
        foreach ($keys as $key) {
            self::clear($companyId, $key);
        }
    }

    /**
     * تكوين مفتاح الكاش الفريد للمورد والشركة.
     */
    protected static function getCacheKey(int $companyId, string $key): string
    {
        return "saas_usage_co_{$companyId}_{$key}";
    }

    /**
     * تسجيل مستمعين (Observers) لمراقبة النماذج وتحديث الكاش تلقائياً.
     */
    public static function registerObservers(): void
    {
        // 1. مراقبة الفواتير
        try {
            if (class_exists(\App\Models\Invoice::class)) {
                \App\Models\Invoice::created(function ($invoice) {
                    self::clear((int) $invoice->company_id, 'invoices');
                });
                \App\Models\Invoice::deleted(function ($invoice) {
                    self::clear((int) $invoice->company_id, 'invoices');
                });
            }
        } catch (\Throwable $e) {
        }

        // 2. مراقبة المنتجات
        try {
            if (class_exists(\Modules\Inventory\Models\Product::class)) {
                \Modules\Inventory\Models\Product::created(function ($product) {
                    self::clear((int) $product->company_id, 'products');
                });
                \Modules\Inventory\Models\Product::deleted(function ($product) {
                    self::clear((int) $product->company_id, 'products');
                });
            }
        } catch (\Throwable $e) {
        }

        // 3. مراقبة المستخدمين والموظفين
        try {
            if (class_exists(\App\Models\CompanyUser::class)) {
                \App\Models\CompanyUser::created(function ($companyUser) {
                    self::clear((int) $companyUser->company_id, 'users');
                });
                \App\Models\CompanyUser::deleted(function ($companyUser) {
                    self::clear((int) $companyUser->company_id, 'users');
                });
            }
        } catch (\Throwable $e) {
        }

        // 4. مراقبة المستودعات والمخازن
        try {
            if (class_exists(\Modules\Inventory\Models\Warehouse::class)) {
                \Modules\Inventory\Models\Warehouse::created(function ($warehouse) {
                    self::clear((int) $warehouse->company_id, 'warehouses');
                });
                \Modules\Inventory\Models\Warehouse::deleted(function ($warehouse) {
                    self::clear((int) $warehouse->company_id, 'warehouses');
                });
            }
        } catch (\Throwable $e) {
        }
    }
}

<?php

namespace App\Services\SaaS;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// تعليق عربي: سجل ديناميكي لإدارة وتحميل كلاسات حساب الموارد (Drivers) من قاعدة البيانات مع وجود معالجة تلقائية للحالات الافتراضية.
class UsageDriverRegistry
{
    /**
     * تعريف الكلاسات الافتراضية لضمان استمرارية التشغيل.
     */
    protected static array $fallbackDrivers = [
        'users' => \App\Services\SaaS\Drivers\UsersUsageDriver::class,
        'products' => \App\Services\SaaS\Drivers\ProductsUsageDriver::class,
        'invoices' => \App\Services\SaaS\Drivers\InvoicesUsageDriver::class,
        'warehouses' => \App\Services\SaaS\Drivers\WarehousesUsageDriver::class,
        'whatsapp_messages' => \App\Services\SaaS\Drivers\WhatsappMessagesUsageDriver::class,
        'api_calls' => \App\Services\SaaS\Drivers\ApiCallsUsageDriver::class,
        'storage_size' => \App\Services\SaaS\Drivers\StorageSizeUsageDriver::class,
    ];

    /**
     * استرجاع كائن الـ Driver المرتبط بمفتاح المورد المعين.
     */
    public static function getDriver(string $key): ?Contracts\UsageDriverInterface
    {
        $driverClass = null;

        // محاولة جلب كلاس الحساب من قاعدة البيانات
        try {
            if (Schema::hasTable('usage_metrics')) {
                $metric = DB::table('usage_metrics')
                    ->where('key', $key)
                    ->where('status', true)
                    ->first();
                if ($metric) {
                    $driverClass = $metric->resolver_class;
                }
            }
        } catch (\Throwable $e) {
            // تجاوز الخطأ في حال لم تكن الجداول مهيأة بعد
        }

        // العودة للافتراضي في حال لم يعثر عليه في قاعدة البيانات
        if (!$driverClass && isset(self::$fallbackDrivers[$key])) {
            $driverClass = self::$fallbackDrivers[$key];
        }

        if ($driverClass && class_exists($driverClass)) {
            return app($driverClass);
        }

        return null;
    }
}

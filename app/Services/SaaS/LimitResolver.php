<?php

namespace App\Services\SaaS;

//   كلاس المحاذاة والتوافق الخلفي (Backward Compatibility) لتوجيه الاستعلامات إلى SaaSEngine الجديد.
class LimitResolver
{
    /**
     * حساب الاستهلاك الحالي لمورد معين للشركة.
     */
    public static function getCurrentUsage(int $companyId, string $resource): int
    {
        return CachedUsageCounter::get($companyId, $resource);
    }

    /**
     * التحقق هل استهلاك الشركة الحالي ضمن المسموح به للباقة النشطة.
     */
    public static function isWithinLimit(int $companyId, string $resource): bool
    {
        return SaaSEngine::isWithinLimit($companyId, $resource);
    }

    /**
     * استرجاع مصفوفة إحصائية متكاملة لجميع الاستهلاكات والحدود للشركة.
     */
    public static function getSubscriptionUsageMatrix(int $companyId): array
    {
        return SaaSEngine::getSubscriptionUsageMatrix($companyId);
    }
}

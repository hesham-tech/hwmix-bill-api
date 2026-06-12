<?php

namespace App\Services\SaaS;

//   خدمة التحقق من صلاحيات الباقة وميزات النظام المتاحة بالتوافق مع SaaSEngine الجديد.
class FeatureAccessService
{
    /**
     * التحقق هل الباقة الحالية للشركة تدعم ميزة معينة.
     */
    public static function hasAccess(int $companyId, string $featureKey): bool
    {
        return SaaSEngine::hasFeature($companyId, $featureKey);
    }
}

<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;

// تعليق عربي: كلاس حساب عدد رسائل الواتساب المرسلة شهرياً لمتابعة استهلاك خدمة الإشعارات التلقائية.
class WhatsappMessagesUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد رسائل الواتساب المرسلة خلال الشهر الحالي (مبني كـ Stub مؤقتاً).
     */
    public function resolve(int $companyId): int
    {
        // يمكن لاحقاً ربطه بجدول سجل رسائل الواتساب الفعلي
        return 0;
    }
}

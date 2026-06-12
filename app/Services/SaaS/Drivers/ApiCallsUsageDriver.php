<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;

//   كلاس حساب عدد طلبات واستدعاءات واجهات برمجة التطبيقات (API Calls) المستهلكة شهرياً.
class ApiCallsUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد استدعاءات الـ API خلال الشهر الحالي (Stub مؤقتاً).
     */
    public function resolve(int $companyId): int
    {
        return 0;
    }
}

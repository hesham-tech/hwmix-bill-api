<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;

//   كلاس حساب المساحة التخزينية المستهلكة لرفع الملفات والوسائط والمستندات للشركة بالمنصة بالميجابايت.
class StorageSizeUsageDriver implements UsageDriverInterface
{
    /**
     * حساب حجم التخزين المستهلك بالميجابايت (Stub مؤقتاً).
     */
    public function resolve(int $companyId): int
    {
        return 0;
    }
}

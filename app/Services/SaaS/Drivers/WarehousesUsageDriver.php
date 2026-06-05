<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;
use Modules\Inventory\Models\Warehouse;

// تعليق عربي: كلاس حساب عدد المخازن والمستودعات النشطة الخاصة بالشركة المشتركة.
class WarehousesUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد المخازن للشركة.
     */
    public function resolve(int $companyId): int
    {
        return Warehouse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->count();
    }
}

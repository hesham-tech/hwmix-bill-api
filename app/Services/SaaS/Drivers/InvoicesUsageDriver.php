<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;
use App\Models\Invoice;
use Carbon\Carbon;

// تعليق عربي: كلاس حساب عدد الفواتير الصادرة خلال الشهر الحالي للشركة المشتركة لمتابعة حدود الاستهلاك المتجدد.
class InvoicesUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد الفواتير المصدرة خلال الشهر الميلادي الحالي.
     */
    public function resolve(int $companyId): int
    {
        return Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
    }
}

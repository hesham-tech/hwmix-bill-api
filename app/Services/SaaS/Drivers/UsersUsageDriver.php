<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;
use App\Models\CompanyUser;

// تعليق عربي: كلاس حساب عدد الموظفين والمستخدمين النشطين المرتبطين بالشركة المشتركة.
class UsersUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد المستخدمين الحاليين للشركة.
     */
    public function resolve(int $companyId): int
    {
        return CompanyUser::where('company_id', $companyId)->count();
    }
}

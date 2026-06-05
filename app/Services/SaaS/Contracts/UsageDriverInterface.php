<?php

namespace App\Services\SaaS\Contracts;

// تعليق عربي: واجهة برمجية موحدة لتعريف طريقة حساب الاستهلاك لجميع موارد الباقة والاشتراكات.
interface UsageDriverInterface
{
    /**
     * حساب الاستهلاك الحالي للشركة لمورد معين.
     *
     * @param int $companyId
     * @return int
     */
    public function resolve(int $companyId): int;
}

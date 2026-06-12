<?php

namespace App\Services\SaaS\Drivers;

use App\Services\SaaS\Contracts\UsageDriverInterface;
use Modules\Inventory\Models\Product;

//   كلاس حساب إجمالي عدد الأصناف والمنتجات المضافة في مخزون الشركة المشتركة.
class ProductsUsageDriver implements UsageDriverInterface
{
    /**
     * حساب عدد المنتجات للشركة.
     */
    public function resolve(int $companyId): int
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->count();
    }
}

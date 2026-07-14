<?php

namespace App\Services;

use App\Models\User;
use Modules\Inventory\Models\Warehouse;

/**
 * خدمة حل وتحديد المستودع الافتراضي المفضل للمستخدم أو الفرع.
 */
class DefaultWarehouseResolver
{
    /**
     * تحديد المستودع الافتراضي للمستخدم بناءً على فرعه والشركة
     */
    public function resolve(User $user, ?int $companyId = null, ?int $branchId = null): ?Warehouse
    {
        $companyId = $companyId ?? $user->active_company_id;
        if (!$companyId) {
            return null;
        }

        $branchId = $branchId ?? config('app.active_branch_id') ?? $user->branch_id;
        if ($branchId === 'all') {
            $branchId = $user->branch_id;
        }

        // 1. جلب التفضيل المخزن في جدول العضوية بالفرع (branch_user.default_warehouse_id)
        if ($branchId) {
            $membership = $user->branchMembership($branchId);
            $defaultWarehouseId = $membership ? $membership->default_warehouse_id : null;

            if ($defaultWarehouseId) {
                $warehouse = Warehouse::withoutGlobalScopes()
                    ->where('id', $defaultWarehouseId)
                    ->where('company_id', $companyId)
                    ->first();
                if ($warehouse) {
                    return $warehouse;
                }
            }
        }

        // 2. التراجع (Fallback) للمستودع الافتراضي العام للفرع
        if ($branchId) {
            $branchDefault = Warehouse::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first();
            if ($branchDefault) {
                return $branchDefault;
            }

            // 3. التراجع لأول مستودع بالفرع
            $branchFirst = Warehouse::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('company_id', $companyId)
                ->first();
            if ($branchFirst) {
                return $branchFirst;
            }
        }

        // 4. التراجع لأي مستودع بالشركة ككل
        return Warehouse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();
    }
}

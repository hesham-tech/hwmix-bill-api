<?php

namespace App\Services;

use App\Models\User;
use Modules\Accounting\Models\CashBox;
use App\Enums\CashBoxStatus;

/**
 * خدمة حل وتحديد الخزنة الافتراضية المفضلة للمستخدم (Default CashBox Resolver)
 */
class DefaultCashBoxResolver
{
    /**
     * تحديد الخزينة الافتراضية للشركة والفرع الحالي للمستخدم
     */
    public function resolve(User $user, ?int $companyId = null, ?int $branchId = null): ?CashBox
    {
        $companyId = $companyId ?? $user->active_company_id;
        if (!$companyId) {
            return null;
        }

        $branchId = $branchId ?? $user->branch_id;

        // 1. جلب التفضيل المخزن في جدول العضوية بالفرع (branch_user.default_cash_box_id)
        if ($branchId) {
            $membership = $user->branchMembership($branchId);
            $defaultCashBoxId = $membership ? $membership->default_cash_box_id : null;

            if ($defaultCashBoxId) {
                $defaultBox = CashBox::withoutGlobalScopes()
                    ->where('id', $defaultCashBoxId)
                    ->where('status', CashBoxStatus::ACTIVE)
                    ->first();

                if ($defaultBox && $defaultBox->company_id === $companyId) {
                    // التحقق من تطابق الفرع للمستخدمين العاديين
                    if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company'))) {
                        if ($defaultBox->branch_id === $branchId) {
                            return $defaultBox;
                        }
                    } else {
                        return $defaultBox;
                    }
                }
            }
        }

        // 2. التراجع (Fallback) للبحث عن عهدته النقدية الشخصية النشطة للشركة والفرع
        $personalQuery = CashBox::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', CashBoxStatus::ACTIVE);

        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company'))) {
            if ($branchId) {
                $personalQuery->where('branch_id', $branchId);
            }
        }

        $personalBox = $personalQuery->first();
        if ($personalBox) {
            return $personalBox;
        }

        // 3. التراجع للبحث في الخزن المشتركة التي يملك المستخدم صلاحية عليها في الفرع والشركة
        $sharedQuery = CashBox::withoutGlobalScopes()
            ->whereNull('user_id')
            ->where('company_id', $companyId)
            ->where('status', CashBoxStatus::ACTIVE)
            ->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });

        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company'))) {
            if ($branchId) {
                $sharedQuery->where('branch_id', $branchId);
            }
        }

        return $sharedQuery->first();
    }
}

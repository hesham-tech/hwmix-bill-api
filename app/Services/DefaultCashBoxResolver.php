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
    public function resolve(User $user, ?int $companyId = null): ?CashBox
    {
        $companyId = $companyId ?? $user->active_company_id;
        if (!$companyId) {
            return null;
        }

        // 1. جلب التفضيل المخزن في جدول المستخدمين (users.default_cash_box_id)
        if ($user->default_cash_box_id) {
            $defaultBox = CashBox::withoutGlobalScopes()
                ->where('id', $user->default_cash_box_id)
                ->where('status', CashBoxStatus::ACTIVE)
                ->first();

            if ($defaultBox && $defaultBox->company_id === $companyId) {
                // التحقق من تطابق الفرع للمستخدمين العاديين
                if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company'))) {
                    if ($defaultBox->branch_id === $user->branch_id) {
                        return $defaultBox;
                    }
                } else {
                    return $defaultBox;
                }
            }
        }

        // 2. التراجع (Fallback) للبحث عن عهدته النقدية الشخصية النشطة للشركة والفرع
        $personalQuery = CashBox::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', CashBoxStatus::ACTIVE);

        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company'))) {
            $personalQuery->where('branch_id', $user->branch_id);
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
            $sharedQuery->where('branch_id', $user->branch_id);
        }

        return $sharedQuery->first();
    }
}

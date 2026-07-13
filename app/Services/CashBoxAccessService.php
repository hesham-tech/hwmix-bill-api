<?php

namespace App\Services;

use App\Models\User;
use Modules\Accounting\Models\CashBox;
use App\Enums\CashBoxStatus;

/**
 * خدمة التحقق من ترخيص وصلاحيات الوصول للخزن (CashBox Access Service)
 */
class CashBoxAccessService
{
    /**
     * التحقق من إمكانية وصول المستخدم للخزينة لإجراء الحركات المالية
     */
    public function canAccess(User $user, $cashBox): bool
    {
        // 1. الخزن غير النشطة أو المؤرشفة أو المغلقة لا يمكن الوصول إليها للعمليات المالية
        if ($cashBox->status !== CashBoxStatus::ACTIVE) {
            return false;
        }

        // 2. التحقق من تطابق الشركة
        if ($cashBox->company_id !== $user->active_company_id) {
            return false;
        }

        // 3. حسابات السوبر أدمن ومديري الشركات يمتلكون وصولاً كاملاً لجميع الخزن النشطة التابعة لشركتهم
        if ($user->hasPermissionTo(perm_key('admin.super')) || $user->hasPermissionTo(perm_key('admin.company'))) {
            return true;
        }

        // 4. التحقق من تطابق الفرع المسموح للموظف العادي
        if ($cashBox->branch_id !== $user->branch_id) {
            return false;
        }

        // 5. إذا كانت الخزينة عهدة شخصية: يجب أن تكون مملوكة للمستخدم نفسه
        if ($cashBox->user_id !== null) {
            return $cashBox->user_id === $user->id;
        }

        // 6. إذا كانت الخزينة مشتركة: يجب أن يكون الموظف مصرحاً له بجدول cash_box_user
        return $cashBox->users()->where('users.id', $user->id)->exists();
    }
}

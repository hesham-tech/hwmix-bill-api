<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait لتطبيق عزل البيانات حسب الفرع النشط تلقائياً.
 */
trait FilterableByBranch
{
    /**
     * Boot the trait to apply global scope.
     */
    public static function bootFilterableByBranch()
    {
        static::addGlobalScope('branch_filter', function (Builder $builder) {
            $user = Auth::user();
            
            if (!$user) {
                return;
            }

            // سوبر أدمن يرى كل شيء إذا لم يحدد فرعاً معيناً
            if ($user->hasPermissionTo(perm_key('admin.super')) && !config('app.active_branch_id')) {
                return;
            }

            $activeBranchId = config('app.active_branch_id');
            $allowedBranchIds = $user->getAllowedBranchIds();

            if ($activeBranchId === 'all') {
                // إذا اختار عرض كل الفروع
                if ($user->hasPermissionTo(perm_key('admin.company'))) {
                    // مدير الشركة يرى كل فروع شركته، لا حاجة لفلتر الفرع لأن فلتر الشركة يعمل
                    return;
                } else {
                    // الموظف العادي يرى الفروع المخصصة له فقط
                    $builder->whereIn($builder->getQuery()->from . '.branch_id', $allowedBranchIds);
                    return;
                }
            } elseif ($activeBranchId) {
                // إذا اختار فرعاً محدداً، يجب التأكد أنه ضمن فنياته المسموحة أو أنه مدير شركة
                if ($user->hasPermissionTo(perm_key('admin.company')) || in_array($activeBranchId, $allowedBranchIds)) {
                    $builder->where($builder->getQuery()->from . '.branch_id', $activeBranchId);
                } else {
                    // محاولة وصول لفرع غير مصرح به
                    $builder->whereRaw('1 = 0');
                }
                return;
            }

            // إذا لم يتم تمرير هيدر، نستخدم الفرع الافتراضي للمستخدم أو أول فرع في القائمة
            if ($user->branch_id) {
                $builder->where($builder->getQuery()->from . '.branch_id', $user->branch_id);
            } elseif (!empty($allowedBranchIds)) {
                $builder->where($builder->getQuery()->from . '.branch_id', $allowedBranchIds[0]);
            } else {
                $builder->whereRaw('1 = 0'); // لا يوجد فروع
            }
        });
    }

    /**
     * Scope a query to only include specific branch data.
     */
    public function scopeByBranch(Builder $query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}

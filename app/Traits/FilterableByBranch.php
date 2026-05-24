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

            // سوبر أدمن يرى كل شيء فقط في حال عدم اختيار شركة نشطة
            if ($user->hasPermissionTo(perm_key('admin.super')) && !$user->active_company_id) {
                return;
            }

            $activeBranchId = config('app.active_branch_id');
            
            $allowedBranchIds = $user->hasPermissionTo(perm_key('admin.super'))
                ? \Illuminate\Support\Facades\DB::table('branches')->where('company_id', $user->active_company_id)->pluck('id')->toArray()
                : $user->getAllowedBranchIds();

            if ($activeBranchId === 'all') {
                // إذا اختار عرض كل الفروع
                if ($user->hasPermissionTo(perm_key('admin.company')) || $user->hasPermissionTo(perm_key('admin.super'))) {
                    // مدير الشركة أو السوبر أدمن يرى كل فروع شركته
                    return;
                } else {
                    // الموظف العادي يرى الفروع المخصصة له فقط
                    $builder->whereIn($builder->getQuery()->from . '.branch_id', $allowedBranchIds);
                    return;
                }
            } elseif ($activeBranchId) {
                // إذا اختار فرعاً محدداً، يجب التأكد أنه ضمن فنياته المسموحة أو أنه مدير شركة أو سوبر أدمن
                if ($user->hasPermissionTo(perm_key('admin.company')) || $user->hasPermissionTo(perm_key('admin.super')) || in_array($activeBranchId, $allowedBranchIds)) {
                    $builder->where($builder->getQuery()->from . '.branch_id', $activeBranchId);
                } else {
                    // محاولة وصول لفرع غير مصرح به
                    $builder->whereRaw('1 = 0');
                }
                return;
            }

            // إذا لم يتم تمرير هيدر، نستخدم الفرع الافتراضي للمستخدم أو أول فرع في القائمة
            if ($user->hasPermissionTo(perm_key('admin.company')) || $user->hasPermissionTo(perm_key('admin.super'))) {
                // مدير الشركة أو السوبر أدمن له حق الوصول لكل بيانات شركته، لا يتم حقن فلتر الفروع
                return;
            } elseif ($user->branch_id) {
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

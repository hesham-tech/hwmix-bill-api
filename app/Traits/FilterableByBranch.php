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
            // 1. الأولوية للفرع المحدد في الكونتكست (X-Branch-Id header)
            $activeBranchId = config('app.active_branch_id');

            if ($activeBranchId) {
                $builder->where($builder->getQuery()->from . '.branch_id', $activeBranchId);
                return;
            }

            // 2. إذا لم يوجد كونتكست، نستخدم فرع المستخدم الموثق (إذا لم يكن سوبر أدمن)
            $user = Auth::user();
            if ($user && !$user->hasPermissionTo(perm_key('admin.super'))) {
                $branchId = $user->branch_id;
                if ($branchId) {
                    $builder->where($builder->getQuery()->from . '.branch_id', $branchId);
                }
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

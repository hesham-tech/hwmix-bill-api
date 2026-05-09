<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * سكوب لعزل البيانات حسب الفرع النشط.
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // إذا كان هناك فرع نشط معين في الكونتكست، نستخدمه
        $activeBranchId = config('app.active_branch_id');

        if ($activeBranchId) {
            $builder->where($model->getTable() . '.branch_id', $activeBranchId);
            return;
        }

        // إذا لم يكن هناك فرع محدد في الكونتكست، نتحقق من فرع المستخدم الموثق
        $authUser = Auth::user();
        if ($authUser && $authUser->branch_id) {
            $builder->where($model->getTable() . '.branch_id', $authUser->branch_id);
        }
    }
}

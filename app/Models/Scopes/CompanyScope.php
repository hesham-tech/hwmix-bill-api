<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * سكوب لعزل استعلامات قاعدة البيانات تلقائياً حسب الشركة النشطة للمستخدم.
 */
class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(\App\Services\CurrentCompanyResolver::class)->resolve();

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }
    }
}

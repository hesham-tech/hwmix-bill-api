<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

trait FilterableByCompany
{
    /**
     * Boot the trait to apply global scope.
     */
    public static function bootFilterableByCompany()
    {
        static::addGlobalScope('company_filter', function (Builder $builder) {
            $user = Auth::user();

            // Only apply if user is authenticated and not a super admin
            // and the model has a company_id column
            if ($user && !$user->hasPermissionTo(perm_key('admin.super'))) {
                $activeCompanyId = $user->company_id;
                if ($activeCompanyId) {
                    $builder->where($builder->getQuery()->from . '.company_id', $activeCompanyId);
                }
            }
        });
    }

    /**
     * Scope a query to only include specific company data.
     */
    public function scopeByCompany(Builder $query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

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

            if ($user) {
                $activeCompanyId = $user->active_company_id;
                if ($activeCompanyId) {
                    $builder->where($builder->getQuery()->from . '.company_id', $activeCompanyId);
                } elseif (!$user->hasPermissionTo(perm_key('admin.super'))) {
                    $builder->whereRaw('1 = 0');
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

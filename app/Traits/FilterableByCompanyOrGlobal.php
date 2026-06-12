<?php

namespace App\Traits;

//   ترايت مخصص لتصفية وعرض السجلات الخاصة بالشركة بالإضافة للسجلات العامة المشتركة للنظام
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

trait FilterableByCompanyOrGlobal
{
    /**
     * Boot the trait to apply global scope.
     */
    public static function bootFilterableByCompanyOrGlobal()
    {
        static::addGlobalScope('company_or_global_filter', function (Builder $builder) {
            $user = Auth::user();

            if ($user) {
                $activeCompanyId = $user->active_company_id;
                if ($activeCompanyId) {
                    $table = $builder->getQuery()->from;
                    $builder->where(function (Builder $query) use ($table, $activeCompanyId) {
                        $query->where($table . '.company_id', $activeCompanyId)
                            ->orWhere($table . '.is_global', true);
                    });
                } elseif (!$user->hasPermissionTo(perm_key('admin.super'))) {
                    // Non-superadmins without active company see nothing
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

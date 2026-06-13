<?php
// تصفية السجلات بناءً على الشركة النشطة أو السجلات العامة للنظام (حيث تكون الشركة فارغة)
namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

trait FilterableByCompanyOrSystem
{
    /**
     * تطبيق نطاق تصفية الشركة أو النظام تلقائياً
     */
    public static function bootFilterableByCompanyOrSystem()
    {
        static::addGlobalScope('company_or_system_filter', function (Builder $builder) {
            $user = Auth::user();

            if ($user) {
                $activeCompanyId = $user->active_company_id;
                if ($activeCompanyId) {
                    $table = $builder->getQuery()->from;
                    $builder->where(function (Builder $query) use ($table, $activeCompanyId) {
                        $query->where($table . '.company_id', $activeCompanyId)
                            ->orWhereNull($table . '.company_id');
                    });
                } elseif (!$user->hasPermissionTo(perm_key('admin.super'))) {
                    $builder->whereRaw('1 = 0');
                }
            }
        });
    }

    /**
     * تحديد الاستعلام بنطاق شركة معينة أو السجلات العامة
     */
    public function scopeByCompany(Builder $query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->orWhereNull('company_id');
        });
    }
}

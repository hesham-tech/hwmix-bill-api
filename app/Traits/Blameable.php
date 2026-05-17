<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Blameable
{
    public static function bootBlameable()
    {
        static::creating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            // إضافة created_by إذا كان العمود موجود
            if (Schema::hasColumn($model->getTable(), 'created_by') && blank($model->created_by)) {
                $model->created_by = Auth::id();
            }

            // إضافة company_id إذا كان العمود موجود
            if (Schema::hasColumn($model->getTable(), 'company_id') && blank($model->company_id)) {
                $model->company_id = Auth::user()->active_company_id;
            }
        });
    }
}

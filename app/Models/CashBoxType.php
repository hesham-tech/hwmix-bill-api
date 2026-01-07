<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[ScopedBy([CompanyScope::class])]
class CashBoxType extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable;
    protected $table = 'cash_box_types';

    protected $fillable = [
        'name',
        'description',
        'is_system',
        'is_active',
        'company_id',
        'created_by',
    ];

    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class, 'cash_box_type_id');
    }

    /**
     * Scope للحصول على أنواع الصناديق النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

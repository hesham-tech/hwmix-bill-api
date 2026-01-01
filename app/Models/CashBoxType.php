<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashBoxType extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions;
    protected $table = 'cash_box_types';

    protected $fillable = [
        'name',
        'description',
        'is_system',
        'is_active',
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

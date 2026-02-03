<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Models\Scopes\CompanyScope;
use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashBoxType extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "نوع خزنة ({$this->name})";
    }
    protected $table = 'cash_box_types';

    protected $fillable = [
        'name',
        'description',
        'is_system',
        'is_active',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class, 'cash_box_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope للحصول على أنواع الصناديق النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace Modules\Companies\Models;

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Company;
use Modules\Inventory\Models\Warehouse;
use Modules\Accounting\Models\CashBox;

/**
 * موديل لإدارة فروع الشركات.
 */
class Branch extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompany;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean', // تم تغيير is_main إلى is_default في الهجرات
    ];

    /**
     * علاقة الفرع بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * علاقة الفرع بالمستخدمين (الموظفين) عبر الجدول الوسيط.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user', 'branch_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * علاقة الفرع بالمستودعات.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * علاقة الفرع بالصناديق المالية.
     */
    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class);
    }

    /**
     * ميثود للحصول على اسم الفرع لأغراض السجلات.
     */
    public function logLabel()
    {
        return "الفرع: {$this->name}";
    }
}

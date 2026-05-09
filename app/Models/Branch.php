<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * موديل لإدارة فروع الشركات.
 */
class Branch extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];

    /**
     * علاقة الفرع بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * علاقة الفرع بالمستخدمين (الموظفين).
     */
    public function users()
    {
        return $this->hasMany(User::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * يمثل المعاملة أو العملية المالية الحاكمة التي ترتبط بها كافة قيود الأستاذ وحركات النقدية والذمم.
 */
class FinancialOperation extends Model
{
    protected $guarded = [];

    // يتم تحديد أن المفتاح الأساسي ليس تلقائي الزيادة وأنه نصي (UUID)
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * الشركة التابع لها
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * الموظف المنشئ للعملية
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستند المصدر Polymorphic
     */
    public function source()
    {
        return $this->morphTo();
    }
}

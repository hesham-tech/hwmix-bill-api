<?php

namespace Modules\Payment\Models;

// تعليق عربي: موديل بوابة الدفع الإلكتروني لتخزين إعدادات الربط للشركات.

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class PaymentGateway extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompany;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'config' => 'array',
    ];

    /**
     * علاقة البوابة بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ميثود للحصول على اسم البوابة لأغراض السجلات.
     */
    public function logLabel()
    {
        return "بوابة الدفع: {$this->name} ({$this->driver})";
    }
}

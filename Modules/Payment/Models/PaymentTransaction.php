<?php

namespace Modules\Payment\Models;

// تعليق عربي: موديل معاملة الدفع الإلكتروني لتسجيل وتتبع تفاصيل المعاملات المالية للفواتير والاشتراكات.

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Branch;

class PaymentTransaction extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompany, FilterableByBranch;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
    ];

    /**
     * علاقة المعاملة ببوابة الدفع المستخدمة.
     */
    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * علاقة متعددة الأشكال بالكيان القابل للدفع (مثل الفاتورة أو الاشتراك).
     */
    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * علاقة المعاملة بالفرع.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * ميثود للحصول على تفاصيل العملية لأغراض السجلات.
     */
    public function logLabel()
    {
        return "معاملة دفع بقيمة: {$this->amount} {$this->currency} - الحالة: {$this->status}";
    }
}

<?php

namespace Modules\Payment\Models;

//   موديل معاملة الدفع الإلكتروني لتسجيل وتتبع تفاصيل المعاملات المالية للفواتير والاشتراكات.

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
    // Collection attempts have their own audit trail and must not inherit a buyer's ERP branch/activity feed.
    use HasFactory, SoftDeletes, Blameable, Scopes, FilterableByCompany;

    protected $guarded = [];
    protected $hidden = ['gateway_config_snapshot'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'gateway_config_snapshot' => 'encrypted:array',
        'gateway_test_mode' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * علاقة المعاملة ببوابة الدفع المستخدمة.
     */
    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id')->withoutGlobalScopes()->withTrashed();
    }

    /**
     * علاقة متعددة الأشكال بالكيان القابل للدفع (مثل الفاتورة أو الاشتراك).
     */
    public function payable()
    {
        return $this->morphTo()->withoutGlobalScopes();
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

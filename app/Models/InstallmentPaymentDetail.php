<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Builder;

/**
 *   كلاس يمثل تفاصيل المبالغ المسددة لكل قسط فردي ضمن دفعة السداد، ويدعم العزل غير المباشر بالتبعية لدفعة القسط.
 */
class InstallmentPaymentDetail extends Model
{
    use HasFactory, Scopes, Blameable, \App\Traits\LogsActivity;

    /**
     * نطاق لعزل البيانات حسب الشركة النشطة بالاعتماد على معاملة الدفع الأب.
     */
    public function scopeWhereCompanyIsCurrent(Builder $query): Builder
    {
        return $query->whereHas('installmentPayment', function ($q) {
            $q->whereCompanyIsCurrent();
        });
    }

    /**
     * التحقق مما إذا كان السطر ينتمي للشركة الحالية بالاعتماد على المعاملة الأب.
     */
    public function belongsToCurrentCompany(): bool
    {
        return $this->installmentPayment?->belongsToCurrentCompany() ?? false;
    }

    /**
     * التحقق مما إذا كان السطر قد أنشئ بواسطة المستخدم أو أحد تابعيه بالاعتماد على المعاملة الأب.
     */
    public function createdByUserOrChildren(): bool
    {
        return $this->installmentPayment?->createdByUserOrChildren() ?? false;
    }

    /**
     * التحقق مما إذا كان السطر قد أنشئ بواسطة المستخدم الحالي بالاعتماد على المعاملة الأب.
     */
    public function createdByCurrentUser(): bool
    {
        return $this->installmentPayment?->createdByCurrentUser() ?? false;
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "تفاصيل دفعة قسط ({$this->installment?->installment_number}) - مبلغ: {$this->amount_paid}";
    }

    protected $table = 'installment_payment_details';
    protected $guarded = [];

    public function installmentPayment()
    {
        return $this->belongsTo(InstallmentPayment::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

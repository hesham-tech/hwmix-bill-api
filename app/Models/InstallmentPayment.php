<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\SmartSearch;

/**
 * Installment Payment Model
 */
class InstallmentPayment extends Model
{
    use HasFactory, Scopes, Blameable, \App\Traits\LogsActivity, SmartSearch;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "دفعة أقساط ({$this->payment_date}) - مبلغ: {$this->amount_paid}";
    }

    protected $fillable = [
        'installment_plan_id',
        'company_id',
        'created_by',
        'payment_date',
        'amount_paid',
        'payment_method',
        'notes',
        'cash_box_id',
        'reference_number',
    ];

    // Customer is retrieved via plan
    public function getCustomerAttribute()
    {
        return $this->plan?->customer;
    }

    public function plan()
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(InstallmentPaymentDetail::class, 'installment_payment_id');
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    public function installments()
    {
        return $this->belongsToMany(Installment::class, 'installment_payment_details')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }
}

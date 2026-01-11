<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentPaymentDetail extends Model
{
    use HasFactory, Scopes, Blameable, \App\Traits\LogsActivity;

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

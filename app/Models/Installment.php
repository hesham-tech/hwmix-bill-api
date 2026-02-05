<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperInstallment
 */
class Installment extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "قسط ({$this->installment_number}) - مبلغ: {$this->amount}";
    }

    protected $fillable = [
        'installment_plan_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_at',
        'remaining',
        'created_by',
        'user_id',
        'company_id',
        'invoice_id',
    ];

    protected $casts = [
        'due_date' => 'datetime', // أضف هذا السطر
        'paid_at' => 'datetime',  // أضف هذا السطر
    ];

    // العلاقات
    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function payments()
    {
        return $this->belongsToMany(InstallmentPayment::class, 'installment_payment_details')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }

    public function paymentDetails()
    {
        return $this->hasMany(InstallmentPaymentDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * نطاق للترتيب حسب الأولوية الذكية:
     * 1. المتأخر والمستحق اليوم (Priority 1)
     * 2. القادم (معلق أو مدفوع جزئياً ولم يحن موعده) (Priority 2)
     * 3. المدفوع بالكامل (Priority 3)
     * 4. الملغي (Priority 4)
     */
    public function scopeOrderByPriority($query)
    {
        $now = now();

        return $query->orderByRaw("
            CASE 
                WHEN status NOT IN ('paid', 'canceled') AND due_date <= '{$now}' THEN 1
                WHEN status NOT IN ('paid', 'canceled') AND due_date > '{$now}' THEN 2
                WHEN status = 'paid' THEN 3
                WHEN status = 'canceled' THEN 4
                ELSE 5
            END ASC
        ")->orderBy('due_date', 'asc');
    }
}

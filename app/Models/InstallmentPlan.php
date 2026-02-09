<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // ← ✅ استيراد السوفت دليت

/**
 * InstallmentPlan Model
 */
class InstallmentPlan extends Model
{
    use HasFactory, Blameable, Scopes, SoftDeletes, \App\Traits\LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "خطة تقسيط ({$this->user?->name}) - إجمالي الصافي: {$this->net_amount}";
    }

    protected $fillable = [
        'invoice_id',
        'name',
        'description',
        'user_id',
        'net_amount',
        'down_payment',
        'interest_rate',
        'interest_amount',
        'total_amount',
        'remaining_amount',
        'company_id',
        'created_by',
        'number_of_installments',
        'frequency',
        'installment_amount',
        'start_date',
        'end_date',
        'status',
        'notes',
        'round_step',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // العلاقات زي ما هي 👇
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function installments()
    {
        return $this->hasMany(Installment::class);
    }
    public function customer()
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
    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }

    /**
     * حساب إجمالي المبلغ المحصل (المقدم + ما تم دفعه من أقساط)
     */
    public function getTotalCollectedAttribute()
    {
        $paidInstallments = $this->installments()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->get()
            ->sum(fn($inst) => bcsub($inst->amount, $inst->remaining, 2));

        return bcadd($this->down_payment ?? 0, $paidInstallments, 2);
    }

    /**
     * حساب المتبقي الفعلي (الإجمالي - المحصل الفعلي)
     */
    public function getActualRemainingAttribute()
    {
        return bcsub($this->total_amount, $this->total_collected, 2);
    }

    /**
     * حساب نسبة التقدم في السداد بدقة
     */
    public function getPaymentProgressAttribute()
    {
        if ($this->total_amount <= 0)
            return 0;
        $progress = bcmul(bcdiv($this->total_collected, $this->total_amount, 4), '100', 2);
        return (float) $progress;
    }

    /**
     * حساب مبلغ الفائدة بناءً على النسبة والمدة (للتأكد من وحدة المنطق)
     */
    public function getCalculatedInterestAmountAttribute()
    {
        $net = $this->net_amount ?? 0;
        $rate = $this->interest_rate ?? 0;
        $months = $this->number_of_installments ?? 0;

        $factualRate = bcdiv(bcmul($rate, $months, 4), '12', 4);
        return bcmul($net, bcdiv($factualRate, '100', 4), 2);
    }
}

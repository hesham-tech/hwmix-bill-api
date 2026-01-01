<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes;

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'cash_box_id',
        'amount',
        'payment_date',
        'notes',
        'reference_number',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * التحديث التلقائي للفاتورة عند إضافة/حذف دفعة
     */
    protected static function booted()
    {
        static::created(function ($payment) {
            $payment->updateInvoiceAmounts();
        });

        static::deleted(function ($payment) {
            $payment->updateInvoiceAmounts();
        });

        static::updated(function ($payment) {
            if ($payment->isDirty('amount')) {
                $payment->updateInvoiceAmounts();
            }
        });
    }

    /**
     * تحديث المبالغ في الفاتورة
     */
    public function updateInvoiceAmounts(): void
    {
        $invoice = $this->invoice;
        if (!$invoice) {
            return;
        }

        // حساب إجمالي المدفوعات
        $totalPaid = $invoice->payments()->sum('amount');

        $invoice->paid_amount = $totalPaid;
        $invoice->remaining_amount = $invoice->net_amount - $totalPaid;

        // تحديث حالة الدفع
        $invoice->updatePaymentStatus();

        $invoice->save();
    }

    // ==================== العلاقات ====================

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

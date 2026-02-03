<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\InvoiceObserver;

#[ObservedBy([InvoiceObserver::class])]
class Invoice extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'total_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'issue_date' => 'date',
        'due_date' => 'date',
        'previous_balance' => 'decimal:2',
        'initial_paid_amount' => 'decimal:2',
        'initial_remaining_amount' => 'decimal:2',
        'user_balance_after' => 'decimal:2',
    ];

    // Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PAID = 'paid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELED = 'canceled';
    const STATUS_REFUNDED = 'refunded';

    // Payment Status Constants
    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PARTIALLY_PAID = 'partially_paid';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_OVERPAID = 'overpaid';

    /**
     * تحديث حالة الدفع بناءً على المبالغ
     */
    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount == 0) {
            $this->payment_status = self::PAYMENT_UNPAID;
        } elseif ($this->paid_amount >= $this->net_amount) {
            $this->payment_status = $this->paid_amount > $this->net_amount
                ? self::PAYMENT_OVERPAID
                : self::PAYMENT_PAID;
        } else {
            $this->payment_status = self::PAYMENT_PARTIALLY_PAID;
        }

        $this->save();
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $type = InvoiceType::find($invoice->invoice_type_id);
                $companyId = Auth::user()->company_id;
                $invoice->invoice_number = self::generateInvoiceNumber($type->code, $companyId);
            }

            $invoice->company_id = $invoice->company_id ?? Auth::user()->company_id;
            $invoice->created_by = $invoice->created_by ?? Auth::id();

            // تعيين المبالغ الابتدائية كـ Snapshot لا يتغير
            $invoice->initial_paid_amount = $invoice->paid_amount ?? 0;
            $invoice->initial_remaining_amount = $invoice->remaining_amount ?? 0;
        });

        static::updating(function ($invoice) {
            $invoice->updated_by = Auth::id();
        });
    }

    public static function generateInvoiceNumber($typeCode, $companyId)
    {
        $datePart = now()->format('ymd');
        $lastInvoice = self::where('company_id', $companyId)
            ->whereHas('invoiceType', fn($query) => $query->where('code', $typeCode))
            ->latest('id')
            ->first();
        $lastSerial = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -6) : 0;
        $nextSerial = str_pad($lastSerial + 1, 6, '0', STR_PAD_LEFT);
        return strtoupper(self::shortenTypeCode($typeCode)) . '-' . $datePart . '-' . $companyId . '-' . $nextSerial;
    }

    public static function shortenTypeCode(string $typeCode): string
    {
        $parts = explode('_', $typeCode);
        return count($parts) === 1
            ? substr($typeCode, 0, 4)
            : implode('_', array_map(fn($p) => substr($p, 0, 3), $parts));
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function invoiceType()
    {
        return $this->belongsTo(InvoiceType::class);
    }
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
    public function itemsWithTrashed()
    {
        return $this->hasMany(InvoiceItem::class)->withTrashed();
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
    public function installmentPlan()
    {
        return $this->hasOne(InstallmentPlan::class, 'invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id');
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "الفاتورة ({$this->invoice_number})";
    }

    /**
     * Get the column name for accounting date filtering
     */
    public function getIssueDateColumn(): string
    {
        return 'issue_date';
    }
}

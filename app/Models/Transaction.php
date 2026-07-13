<?php
//   موديل يمثل المعاملات والقيود المالية لحساب الأرصدة وتتبع حركات الخزن - تم التحديث لتنشيط الـ IDE
namespace App\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\TransactionObserver;

#[ObservedBy([TransactionObserver::class])]
class Transaction extends Model
{
    public static bool $preventObserverLog = false;

    use Blameable, Scopes, \App\Traits\LogsActivity, \App\Traits\FilterableByCompany, \App\Traits\FilterableByBranch;
    protected $fillable = [
        'user_id',
        'cashbox_id',
        'target_user_id',
        'target_cashbox_id',
        'created_by',
        'company_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'employee_balance_before',
        'employee_balance_after',
        'client_balance_before',
        'client_balance_after',
        'source_invoice_id',
        'source_installment_id',
        'is_transfer',
        'description',
        'original_transaction_id',
        'branch_id',
        'financial_operation_id',
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->company_id = $transaction->company_id ?? Auth::user()?->active_company_id;
            $transaction->branch_id = $transaction->branch_id ?? config('app.active_branch_id') ?? Auth::user()?->branch_id;
            $transaction->is_transfer = $transaction->is_transfer ?? in_array($transaction->type, ['transfer_out', 'transfer_in', 'reverse_transfer', 'reverse_transfer_out', 'reverse_transfer_in']);

            // ربط معرفات المصدر للفاتورة أو القسط تلقائياً من خلال العملية المالية
            if ($transaction->financial_operation_id && null === $transaction->source_invoice_id && null === $transaction->source_installment_id) {
                $operation = \App\Models\FinancialOperation::withoutGlobalScopes()->find($transaction->financial_operation_id);
                if ($operation) {
                    if (in_array($operation->source_type, ['Modules\Sales\Models\Invoice', 'App\Models\Invoice'])) {
                        $transaction->source_invoice_id = $operation->source_id;
                    } elseif (in_array($operation->source_type, ['App\Models\Installment', 'Modules\Sales\Models\Installment'])) {
                        $transaction->source_installment_id = $operation->source_id;
                    } elseif ($operation->source_type === 'App\Models\InvoicePayment') {
                        $payment = \App\Models\InvoicePayment::withoutGlobalScopes()->find($operation->source_id);
                        if ($payment) {
                            $transaction->source_invoice_id = $payment->invoice_id;
                        }
                    }

                    if (empty($transaction->source_invoice_id) && isset($operation->metadata['source_invoice_id'])) {
                        $transaction->source_invoice_id = $operation->metadata['source_invoice_id'];
                    }
                    if (empty($transaction->source_invoice_id) && isset($operation->metadata['invoice_id'])) {
                        $transaction->source_invoice_id = $operation->metadata['invoice_id'];
                    }
                }
            }
        });
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(\Modules\Sales\Models\Invoice::class, 'source_invoice_id');
    }

    public function sourceInstallment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Installment::class, 'source_installment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->customer();
    }

    public function targetCustomer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->targetCustomer();
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'cashbox_id');
    }

    public function targetCashbox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'target_cashbox_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    public function reverseTransactions()
    {
        return $this->hasMany(Transaction::class, 'original_transaction_id');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->whereHas('user', function ($userQuery) use ($companyId) {
            $userQuery->where('company_id', $companyId);
        });
    }

    public function scopeByCreator($query, $creatorId)
    {
        return $query->whereHas('user', function ($userQuery) use ($creatorId) {
            $userQuery->where('created_by', $creatorId);
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }



    /**
     *   تسمية المعاملة وعرض تفاصيلها المحاسبية
     */
    public function logLabel()
    {
        $customerName = $this->customer?->nickname ?? $this->customer?->name ?? 'غير محدد';
        $amountFormatted = number_format($this->amount, 2);

        $actionWord = match ($this->type) {
            'deposit' => 'إضافة',
            'withdraw' => 'سحب',
            'transfer_out' => 'تحويل صادر بقيمة',
            'transfer_in' => 'تحويل وارد بقيمة',
            default => 'حركة مالية بمبلغ'
        };

        return "{$actionWord} مبلغ {$amountFormatted} ج من رصيد الحساب ({$customerName}) بسبب: {$this->description}";
    }

    /**
     *   تجاوز دالة logCreated لكتابة صيغة مبنية للمجهول واضحة وتلقائية
     */
    public function logCreated($text)
    {
        $customerName = $this->customer?->nickname ?? $this->customer?->name ?? 'غير محدد';
        $amountFormatted = number_format($this->amount, 2);

        if ($this->type === 'deposit') {
            $description = "تم إضافة مبلغ {$amountFormatted} ج إلى رصيد الحساب ({$customerName}) بسبب: {$this->description}";
        } elseif ($this->type === 'withdraw') {
            $description = "تم سحب مبلغ {$amountFormatted} ج من رصيد الحساب ({$customerName}) بسبب: {$this->description}";
        } elseif ($this->type === 'transfer_out') {
            $targetName = $this->targetCustomer?->nickname ?? $this->targetCustomer?->name ?? 'غير محدد';
            $description = "تم تحويل مبلغ {$amountFormatted} ج من رصيد الحساب ({$customerName}) إلى حساب ({$targetName}) بسبب: {$this->description}";
        } elseif ($this->type === 'transfer_in') {
            $targetName = $this->targetCustomer?->nickname ?? $this->targetCustomer?->name ?? 'غير محدد';
            $description = "تم استلام تحويل بمبلغ {$amountFormatted} ج لحساب ({$customerName}) من حساب ({$targetName}) بسبب: {$this->description}";
        } else {
            $description = "تم تسجيل حركة مالية بمبلغ {$amountFormatted} ج على حساب ({$customerName}) بسبب: {$this->description}";
        }

        $this->recordActivity('انشاء', $description, null, $this->getAttributes());
    }

    /**
     *   تجاوز دالة logUpdated عند التعديل
     */
    public function logUpdated($text)
    {
        $customerName = $this->customer?->nickname ?? $this->customer?->name ?? 'غير محدد';
        $description = "تم تعديل المعاملة المالية رقم #{$this->id} الخاصة بـ ({$customerName})";
        $this->recordActivity('تعديل', $description, $this->getOriginal(), $this->getChanges());
    }

    /**
     *   تجاوز دالة logDeleted عند الحذف
     */
    public function logDeleted($text)
    {
        $customerName = $this->customer?->nickname ?? $this->customer?->name ?? 'غير محدد';
        $description = "تم حذف المعاملة المالية رقم #{$this->id} الخاصة بـ ({$customerName})";
        $this->recordActivity('حذف', $description, $this->getAttributes(), null);
    }
}

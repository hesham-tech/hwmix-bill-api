<?php

namespace Modules\Accounting\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\Accounting\Observers\TransactionObserver;
use App\Models\User;
use App\Models\Company;

#[ObservedBy([TransactionObserver::class])]
/**
 * موديل المعاملات المالية (Transaction) - موديول المحاسبة
 */
class Transaction extends Model
{
    use Blameable, Scopes, LogsActivity, FilterableByCompany, FilterableByBranch;

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
        'description',
        'original_transaction_id',
        'branch_id',
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->company_id = $transaction->company_id ?? Auth::user()->company_id;
            $transaction->branch_id = $transaction->branch_id ?? config('app.active_branch_id') ?? Auth::user()->branch_id;
        });
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

    public function logLabel()
    {
        return "المعاملة #{$this->id} ({$this->type})";
    }

    public function reverseTransfer()
    {
        $senderBox = $this->cashbox;
        $receiverBox = $this->targetCashbox;

        if (!$senderBox || !$receiverBox) {
            throw new Exception("الصناديق المرتبطة بالمعاملة غير موجودة.");
        }

        $senderBox->balance += $this->amount;
        $senderBox->save();

        $receiverBox->balance -= $this->amount;
        $receiverBox->save();
    }

    public function reverseWithdraw()
    {
        $box = $this->cashbox;

        if (!$box) {
            throw new Exception("الصندوق المرتبط بالمعاملة غير موجود.");
        }

        $box->balance += $this->amount;
        $box->save();
    }

    public function reverseDeposit()
    {
        $box = $this->cashbox;

        if (!$box) {
            throw new Exception("الصندوق المرتبط بالمعاملة غير موجود.");
        }

        if ($box->balance < $this->amount) {
            throw new Exception("الرصيد غير كافٍ لعكس العملية.");
        }

        $box->balance -= $this->amount;
        $box->save();
    }
}

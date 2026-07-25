<?php
// موديل يمثل خطوط الاتصال والمحافظ الإلكترونية بأجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HwnixCash\Domain\Enums\LineStatus;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;

class HwnixCashLine extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'daily_withdraw_limit' => 'decimal:2',
        'daily_deposit_limit' => 'decimal:2',
        'monthly_withdraw_limit' => 'decimal:2',
        'monthly_deposit_limit' => 'decimal:2',
        'status' => LineStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(HwnixCashDevice::class, 'device_android_id', 'android_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HwnixCashMessage::class, 'sms_line_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(HwnixCashWalletTransaction::class, 'line_id');
    }

    public function logLabel(): string
    {
        return "خط محفظة كاش هونكس ({$this->phone_number})";
    }

    // --- الدوال المحسوبة ديناميكياً لاستنزاف ومتبقي الحدود دون تخزين في قاعدة البيانات ---

    public function getDailyWithdrawUsedAttribute(): float
    {
        $types = [
            WalletOperationType::CASH_WITHDRAW->value,
            WalletOperationType::TRANSFER->value,
            WalletOperationType::BILL_PAYMENT->value,
            WalletOperationType::MERCHANT_PAYMENT->value,
            WalletOperationType::CARD_PURCHASE->value,
        ];

        return (float) $this->walletTransactions()
            ->where('status', 'success')
            ->whereIn('operation_type', $types)
            ->whereDate('operation_at', today())
            ->sum('amount');
    }

    public function getDailyDepositUsedAttribute(): float
    {
        $types = [
            WalletOperationType::CASH_DEPOSIT->value,
            WalletOperationType::RECEIVE->value,
            WalletOperationType::REFUND->value,
        ];

        return (float) $this->walletTransactions()
            ->where('status', 'success')
            ->whereIn('operation_type', $types)
            ->whereDate('operation_at', today())
            ->sum('amount');
    }

    public function getMonthlyWithdrawUsedAttribute(): float
    {
        $types = [
            WalletOperationType::CASH_WITHDRAW->value,
            WalletOperationType::TRANSFER->value,
            WalletOperationType::BILL_PAYMENT->value,
            WalletOperationType::MERCHANT_PAYMENT->value,
            WalletOperationType::CARD_PURCHASE->value,
        ];

        return (float) $this->walletTransactions()
            ->where('status', 'success')
            ->whereIn('operation_type', $types)
            ->whereMonth('operation_at', now()->month)
            ->whereYear('operation_at', now()->year)
            ->sum('amount');
    }

    public function getMonthlyDepositUsedAttribute(): float
    {
        $types = [
            WalletOperationType::CASH_DEPOSIT->value,
            WalletOperationType::RECEIVE->value,
            WalletOperationType::REFUND->value,
        ];

        return (float) $this->walletTransactions()
            ->where('status', 'success')
            ->whereIn('operation_type', $types)
            ->whereMonth('operation_at', now()->month)
            ->whereYear('operation_at', now()->year)
            ->sum('amount');
    }

    public function getDailyWithdrawRemainingAttribute(): ?float
    {
        return $this->daily_withdraw_limit !== null
            ? max(0, (float) $this->daily_withdraw_limit - $this->daily_withdraw_used)
            : null;
    }

    public function getDailyDepositRemainingAttribute(): ?float
    {
        return $this->daily_deposit_limit !== null
            ? max(0, (float) $this->daily_deposit_limit - $this->daily_deposit_used)
            : null;
    }

    public function getMonthlyWithdrawRemainingAttribute(): ?float
    {
        return $this->monthly_withdraw_limit !== null
            ? max(0, (float) $this->monthly_withdraw_limit - $this->monthly_withdraw_used)
            : null;
    }

    public function getMonthlyDepositRemainingAttribute(): ?float
    {
        return $this->monthly_deposit_limit !== null
            ? max(0, (float) $this->monthly_deposit_limit - $this->monthly_deposit_used)
            : null;
    }
}

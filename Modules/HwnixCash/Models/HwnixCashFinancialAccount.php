<?php
// موديل يمثل الحسابات المالية المباشرة (فودافون كاش، بنك مصر، انستاباي...) المرتبطة بالخط ومصدر الرسائل المعتمد.

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
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;

class HwnixCashFinancialAccount extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_financial_accounts';

    protected $guarded = ['id'];

    protected $casts = [
        'balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'daily_withdraw_limit' => 'decimal:2',
        'daily_deposit_limit' => 'decimal:2',
        'monthly_withdraw_limit' => 'decimal:2',
        'monthly_deposit_limit' => 'decimal:2',
        'daily_withdraw_alert_value' => 'decimal:2',
        'daily_deposit_alert_value' => 'decimal:2',
        'monthly_withdraw_alert_value' => 'decimal:2',
        'monthly_deposit_alert_value' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(HwnixCashLine::class, 'line_id');
    }

    public function messageSource(): BelongsTo
    {
        return $this->belongsTo(HwnixCashMessageSource::class, 'message_source_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(HwnixCashWalletTransaction::class, 'financial_account_id');
    }

    public function logLabel(): string
    {
        return "حساب مالي ({$this->name})";
    }

    // --- حساب استهلاكات ومتبقيات الحدود المالي المباشر بدون تخزين ---

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

    public function getBalanceDifferenceAttribute(): float
    {
        return round((float) $this->actual_balance - (float) $this->balance, 2);
    }

    public function getHasBalanceMismatchAttribute(): bool
    {
        return abs($this->balance_difference) > 0.01;
    }

    // --- حساب عتبة التنبيه وتجاوز الحدود ديناميكياً بدون تخزين حالة مشتقة ---

    public function getLimitAlertThreshold(string $limitType): float
    {
        $limit = (float) ($this->{"{$limitType}_limit"} ?? 0);
        $alertType = $this->{"{$limitType}_alert_type"} ?? 'percentage';
        $alertValue = (float) ($this->{"{$limitType}_alert_value"} ?? 80);

        if ($alertType === 'amount') {
            return $alertValue;
        }

        return ($alertValue / 100) * $limit;
    }

    public function isLimitAlertTriggered(string $limitType): bool
    {
        $limit = (float) ($this->{"{$limitType}_limit"} ?? 0);
        if ($limit <= 0) {
            return false;
        }

        $used = (float) $this->{"{$limitType}_used"};
        $threshold = $this->getLimitAlertThreshold($limitType);

        return $threshold > 0 && $used >= $threshold;
    }

    public function getTriggeredAlertsDetails(): array
    {
        $limitTypes = [
            'daily_withdraw' => 'السحب اليومي',
            'daily_deposit' => 'الإيداع اليومي',
            'monthly_withdraw' => 'السحب الشهري',
            'monthly_deposit' => 'الإيداع الشهري',
        ];

        $triggered = [];
        foreach ($limitTypes as $key => $label) {
            if ($this->isLimitAlertTriggered($key)) {
                $limit = (float) ($this->{"{$key}_limit"} ?? 0);
                $used = (float) $this->{"{$key}_used"};
                $alertType = $this->{"{$key}_alert_type"} ?? 'percentage';
                $alertValue = (float) ($this->{"{$key}_alert_value"} ?? 80);
                $threshold = $this->getLimitAlertThreshold($key);
                $usedPercentage = $limit > 0 ? round(($used / $limit) * 100, 1) : 0;

                $triggered[] = [
                    'limit_key' => $key,
                    'limit_label' => $label,
                    'limit_amount' => $limit,
                    'used_amount' => $used,
                    'remaining_amount' => max(0, $limit - $used),
                    'alert_type' => $alertType,
                    'alert_value' => $alertValue,
                    'threshold_amount' => $threshold,
                    'used_percentage' => $usedPercentage,
                ];
            }
        }

        return $triggered;
    }

    public function getHasAnyAlertTriggeredAttribute(): bool
    {
        return !empty($this->getTriggeredAlertsDetails());
    }
}

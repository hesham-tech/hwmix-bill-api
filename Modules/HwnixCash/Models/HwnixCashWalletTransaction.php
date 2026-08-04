<?php
// موديل يعبر عن معاملات المحافظ الإلكترونية المباشرة في كاش هونكس HwnixCash.

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Domain\Enums\WalletTransactionSource;
use Modules\HwnixCash\Domain\Enums\WalletTransactionStatus;

class HwnixCashWalletTransaction extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_wallet_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'operation_type' => WalletOperationType::class,
        'provider' => WalletProvider::class,
        'status' => WalletTransactionStatus::class,
        'source' => WalletTransactionSource::class,
        'operation_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function ($transaction) {
            if ($transaction->isDirty('parsed_by')) {
                $transaction->parsed_by = $transaction->getOriginal('parsed_by');
            }
            if ($transaction->isDirty('parser_stage')) {
                $transaction->parser_stage = $transaction->getOriginal('parser_stage');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(HwnixCashFinancialAccount::class, 'financial_account_id');
    }

    public function logLabel(): string
    {
        return "معاملة محفظة إلكترونية رقم ({$this->operation_number}) بقيمة {$this->amount} {$this->currency}";
    }
}

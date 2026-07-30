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

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(HwnixCashFinancialAccount::class, 'line_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HwnixCashMessage::class, 'sms_line_id');
    }

    public function logLabel(): string
    {
        return "خط هاتف كاش هونكس ({$this->phone_number})";
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->financialAccounts()->sum('balance');
    }

    public function getTotalActualBalanceAttribute(): float
    {
        return (float) $this->financialAccounts()->sum('actual_balance');
    }
}

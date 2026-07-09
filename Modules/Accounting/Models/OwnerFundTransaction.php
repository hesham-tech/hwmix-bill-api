<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use App\Traits\Scopes;
use App\Models\Company;
use App\Traits\LogsActivity;
use App\Traits\Blameable;
use App\Traits\FilterableByBranch;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * موديل عمليات ومعاملات أموال الملاك والشركاء ورأس المال والقروض
 */
#[ScopedBy([CompanyScope::class])]
class OwnerFundTransaction extends Model
{
    use Scopes, LogsActivity, Blameable, FilterableByBranch, SoftDeletes;

    protected $table = 'owner_fund_transactions';

    protected $fillable = [
        'company_id',
        'branch_id',
        'cashbox_id',
        'user_id',
        'type',
        'amount',
        'entry_date',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Companies\Models\Branch::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'cashbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logLabel()
    {
        return "حركة أملاك ({$this->type}) بمبلغ {$this->amount}";
    }
}

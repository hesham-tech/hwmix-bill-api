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
 * موديل مطابقة وتسوية أرصدة الخزن والحسابات البنكية دفترياً وفعلياً
 */
#[ScopedBy([CompanyScope::class])]
class CashReconciliation extends Model
{
    use Scopes, LogsActivity, Blameable, FilterableByBranch, SoftDeletes;

    protected $table = 'cash_reconciliations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'cashbox_id',
        'reconciliation_date',
        'book_balance',
        'physical_balance',
        'difference',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'book_balance' => 'decimal:2',
        'physical_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'approved_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logLabel()
    {
        return "تسوية الصندوق ({$this->cashbox?->name}) بتاريخ {$this->reconciliation_date} بفارق {$this->difference}";
    }
}

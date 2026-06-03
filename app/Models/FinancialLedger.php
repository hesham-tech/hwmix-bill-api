<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج الدفتر المالي (FinancialLedger) لتسجيل وتتبع كافة الحركات والقيود المالية المباشرة والغير مباشرة.
 */
class FinancialLedger extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity, \App\Traits\FilterableByCompany, \App\Traits\FilterableByBranch;

    protected $table = 'financial_ledger';

    protected $fillable = [
        'entry_date',
        'type',
        'amount',
        'description',
        'source_type',
        'source_id',
        'account_type',
        'company_id',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    protected static function booted()
    {
        static::creating(function ($ledger) {
            $ledger->company_id = $ledger->company_id ?? auth()->user()->active_company_id ?? null;
            $ledger->branch_id = $ledger->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });
    }

    protected $casts = [
        'entry_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * الحصول على المصدر المرتبط بالقيد (Invoice, Expense, etc.)
     */
    public function source()
    {
        return $this->morphTo();
    }
}

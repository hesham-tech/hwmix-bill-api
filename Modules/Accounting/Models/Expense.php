<?php

namespace Modules\Accounting\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\Accounting\Observers\ExpenseObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

#[ObservedBy([ExpenseObserver::class])]
/**
 * موديل المصاريف (Expense) - موديول المحاسبة
 */
class Expense extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes, FilterableByCompany, FilterableByBranch;

    protected $fillable = [
        'expense_category_id',
        'amount',
        'expense_date',
        'payment_method',
        'cash_box_id',
        'reference_number',
        'notes',
        'company_id',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->company_id = $expense->company_id ?? auth()->user()->active_company_id ?? null;
            $expense->branch_id = $expense->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logLabel()
    {
        return "مصروف بقيمة ({$this->amount}) - {$this->category?->name}";
    }
}

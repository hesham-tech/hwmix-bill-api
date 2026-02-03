<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\ExpenseObserver;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

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
        'updated_by'
    ];

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

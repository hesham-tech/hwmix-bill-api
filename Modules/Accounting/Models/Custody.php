<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\FinancialOperation;

/**
 * كلاس لتمثيل العهدة وتتبع المبالغ المنصرفة والمسددة
 */
class Custody extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes, FilterableByCompany, FilterableByBranch;

    protected $table = 'custodies';

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'cashbox_id',
        'amount',
        'settled_cash_amount',
        'settled_expenses_amount',
        'status',
        'issue_date',
        'description',
        'created_by',
        'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashbox()
    {
        return $this->belongsTo(\App\Models\CashBox::class, 'cashbox_id');
    }

    public function expenses()
    {
        return $this->hasMany(\Modules\Accounting\Models\Expense::class, 'custody_id');
    }

    public function financialOperations()
    {
        return $this->morphMany(FinancialOperation::class, 'source');
    }
}

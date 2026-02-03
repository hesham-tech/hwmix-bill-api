<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialLedger extends Model
{
    use HasFactory, Blameable, Scopes;

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
        'updated_by'
    ];

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

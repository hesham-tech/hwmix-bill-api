<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use App\Models\Company;
use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * موديل الإيرادات (Revenue) - موديول المحاسبة
 */
class Revenue extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity, FilterableByCompany, FilterableByBranch;

    public function logLabel()
    {
        return "سجل إيراد ({$this->amount}) - {$this->source_type}";
    }

    protected $fillable = [
        'source_type',
        'source_id',
        'user_id',
        'created_by',
        'wallet_id',
        'company_id',
        'amount',
        'paid_amount',
        'remaining_amount',
        'payment_method',
        'note',
        'revenue_date',
        'branch_id',
    ];

    protected static function booted()
    {
        static::creating(function ($revenue) {
            $revenue->company_id = $revenue->company_id ?? auth()->user()->active_company_id ?? null;
            $revenue->branch_id = $revenue->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wallet()
    {
        return $this->belongsTo(CashBox::class, 'wallet_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

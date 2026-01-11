<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRevenue
 */
class Revenue extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, Scopes, Blameable, \App\Traits\LogsActivity;

    /**
     * Label for activity logs.
     */
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
    ];

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

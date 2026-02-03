<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProfit
 */
class Profit extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, Scopes, Blameable, \App\Traits\LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "سجل ربح ({$this->profit_amount}) - {$this->source_type}";
    }
    protected $fillable = [
        'source_type',
        'source_id',
        'created_by',
        'user_id',
        'company_id',
        'revenue_amount',
        'cost_amount',
        'profit_amount',
        'note',
        'profit_date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

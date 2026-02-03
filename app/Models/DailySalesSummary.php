<?php

namespace App\Models;

use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    use Scopes;

    protected $table = 'daily_sales_summary';

    protected $fillable = [
        'date',
        'company_id',
        'total_revenue',
        'sales_count',
        'total_cogs',
        'total_expenses',
        'gross_profit',
        'net_profit',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'float',
        'total_cogs' => 'float',
        'total_expenses' => 'float',
        'gross_profit' => 'float',
        'net_profit' => 'float',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

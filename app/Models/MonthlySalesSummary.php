<?php

namespace App\Models;

use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

class MonthlySalesSummary extends Model
{
    use Scopes;

    protected $table = 'monthly_sales_summary';

    protected $fillable = [
        'year_month',
        'company_id',
        'total_revenue',
        'total_cogs',
        'total_expenses',
        'net_profit',
        'sales_count',
    ];

    protected $casts = [
        'total_revenue' => 'float',
        'total_cogs' => 'float',
        'total_expenses' => 'float',
        'net_profit' => 'float',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

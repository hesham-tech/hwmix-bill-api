<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Scopes;

class StatsProductSummary extends Model
{
    use Scopes;

    protected $table = 'stats_products_summary';

    protected $fillable = [
        'product_id',
        'company_id',
        'total_sold_quantity',
        'total_revenue',
        'total_profit',
        'total_orders_count',
        'last_sold_at',
    ];

    protected $casts = [
        'total_sold_quantity' => 'float',
        'total_revenue' => 'float',
        'total_profit' => 'float',
        'total_orders_count' => 'integer',
        'last_sold_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

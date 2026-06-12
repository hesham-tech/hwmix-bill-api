<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

//   موديل شرائح أسعار الباقات لإدارة خصومات المدد الطويلة.
class PlanPricingTier extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'plan_pricing_tiers';
    protected $guarded = [];

    protected $casts = [
        'price_per_month' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'min_months' => 'integer',
        'max_months' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}

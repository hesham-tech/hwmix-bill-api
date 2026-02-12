<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Scopes;

class StatsUserProductMatrix extends Model
{
    use Scopes;

    protected $table = 'stats_user_product_matrix';

    protected $fillable = [
        'user_id',
        'product_id',
        'company_id',
        'total_quantity',
        'total_spent',
        'purchase_count',
        'last_purchased_at',
    ];

    protected $casts = [
        'total_quantity' => 'float',
        'total_spent' => 'float',
        'purchase_count' => 'integer',
        'last_purchased_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

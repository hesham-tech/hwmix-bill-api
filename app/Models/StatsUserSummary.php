<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Scopes;

class StatsUserSummary extends Model
{
    use Scopes;

    protected $table = 'stats_users_summary';

    protected $fillable = [
        'user_id',
        'company_id',
        'total_spent',
        'orders_count',
        'favorite_category_id',
        'rfm_score',
        'last_order_at',
    ];

    protected $casts = [
        'total_spent' => 'float',
        'orders_count' => 'integer',
        'rfm_score' => 'float',
        'last_order_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function favoriteCategory()
    {
        return $this->belongsTo(Category::class, 'favorite_category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج الخطط والاشتراكات (Plan) لإدارة باقات النظام المتاحة للشركات ومميزاتها وحدودها.
 */
class Plan extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\Scopes, \App\Traits\Blameable, LogsActivity;
    protected $table = 'plans';
    protected $guarded = [];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_invoices' => 'integer',
    ];

    // علاقات شائعة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function companySubscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function pricingTiers()
    {
        return $this->hasMany(PlanPricingTier::class);
    }
}

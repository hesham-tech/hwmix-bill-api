<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 */
class Subscription extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = [
        'user_id',
        'service_id',
        'plan_id',
        'unique_identifier',
        'company_id',
        'created_by',
        'start_date',
        'starts_at',
        'next_billing_date',
        'ends_at',
        'billing_cycle',
        'price',
        'partial_payment',
        'status',
        'auto_renew',
        'renewal_type',
        'notes'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * التحقق مما إذا كان الاشتراك نشطاً.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * التحقق مما إذا كان الاشتراك منتهياً.
     */
    public function isExpired(): bool
    {
        if (!$this->next_billing_date) {
            return false;
        }
        return \Carbon\Carbon::parse($this->next_billing_date)->isPast();
    }
}

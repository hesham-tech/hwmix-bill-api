<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSubscription
 */
class Subscription extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = [
        'user_id',
        'service_id',
        'start_date',
        'next_billing_date',
        'billing_cycle',
        'price',
        'status',
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

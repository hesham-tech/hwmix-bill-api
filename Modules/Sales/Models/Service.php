<?php

namespace Modules\Sales\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;
use App\Models\Subscription;

use App\Traits\LogsActivity;

/**
 *   كلاس يمثل الخدمات المتاحة للاشتراك وتفاصيل تسعيرها ومدتها داخل موديول المبيعات.
 */
class Service extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "الخدمة ({$this->name})";
    }

    protected $fillable = [
        'name',
        'description',
        'default_price',
        'period_unit',
        'period_value',
        'company_id',
        'created_by',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

// تعليق عربي: موديل اشتراكات الشركات بـ SaaS للتحكم بالباقات والتواريخ والحدود الخاصة بالشركة المشتركة بالمنصة.

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;

class CompanySubscription extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes, LogsActivity;

    protected $table = 'company_subscriptions';
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'price' => 'decimal:2',
        'features' => 'array',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_invoices' => 'integer',
        'auto_renew' => 'boolean',
    ];

    public function logLabel()
    {
        return "اشتراك شركة #{$this->company?->name} - باقة: {$this->plan?->name}";
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * التحقق من فاعلية الاشتراك ونشاطه.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active' && $this->status !== 'trial') {
            return false;
        }

        // إذا كان هناك تاريخ انتهاء، يجب ألا يكون قد مضى
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        // إذا كان في فترة تجربة، يجب ألا تكون قد انتهت
        if ($this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return false;
        }

        return true;
    }
}

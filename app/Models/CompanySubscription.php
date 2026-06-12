<?php

namespace App\Models;

//   موديل اشتراكات الشركات بـ SaaS للتحكم بالباقات والتواريخ والحدود الخاصة بالشركة المشتركة بالمنصة.

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $company_id
 * @property int $plan_id
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon|null $ends_at
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property string|null $price
 * @property int $months
 * @property string|null $coupon_code
 * @property string|null $billing_cycle
 * @property string $status
 * @property int|null $max_users
 * @property int|null $max_products
 * @property int|null $max_invoices
 * @property array|null $features
 * @property bool $auto_renew
 * @property int $created_by
 */
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
        'months' => 'integer',
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

    /**
     * تفعيل الاشتراك عند اتمام الدفع بنجاح.
     */
    public function markAsPaid($transaction = null)
    {
        // 1. إلغاء أي اشتراكات سابقة نشطة للشركة
        self::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'canceled']);

        // 2. تحديث الاشتراك الحالي ليصبح نشطاً
        $startsAt = now();
        $endsAt = $this->calculateEndsAt($startsAt);

        $this->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // إذا كان هناك كود كوبون، نزيد عدد الاستخدامات له عند الدفع والتفعيل
        if ($this->coupon_code) {
            \Illuminate\Support\Facades\DB::table('coupons')
                ->where('code', $this->coupon_code)
                ->increment('used_count');
        }

        // تحديث السياق للشركة
        \Log::info("SaaS: تم تفعيل الاشتراك #{$this->id} للشركة #{$this->company_id} بعد الدفع بنجاح.");
    }

    /**
     * حساب تاريخ انتهاء الاشتراك بناءً على باقته أو الأشهر المختارة.
     */
    protected function calculateEndsAt($startsAt)
    {
        if ($this->months && $this->months > 0) {
            return (clone $startsAt)->addMonths($this->months);
        }

        $plan = $this->plan;
        if (!$plan || !$plan->duration || !$plan->duration_unit) {
            return null;
        }

        $unit = strtolower($plan->duration_unit);
        $endsAt = clone $startsAt;

        if ($unit === 'day' || $unit === 'days') {
            $endsAt->addDays($plan->duration);
        } elseif ($unit === 'month' || $unit === 'months') {
            $endsAt->addMonths($plan->duration);
        } elseif ($unit === 'year' || $unit === 'years') {
            $endsAt->addYears($plan->duration);
        } else {
            $endsAt->addMonths($plan->duration);
        }

        return $endsAt;
    }
}

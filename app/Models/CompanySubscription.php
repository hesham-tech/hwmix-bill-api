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
    protected static function booted(): void {
        static::creating(function(self $subscription) {
            if(!\Illuminate\Support\Facades\Schema::hasColumn('company_subscriptions','feature_overrides'))return;
            if(array_key_exists('feature_overrides',$subscription->getAttributes()))return;
            $previous=self::withoutGlobalScopes()->where('company_id',$subscription->company_id)->whereNull('deleted_at')
                ->whereIn('status',['active','trial','canceled','cancelled','expired'])->with('plan')->latest('id')->first();
            if(!$previous)return;
            if(!$previous->plan||($previous->plan->company_id!==null&&(int)$previous->plan->company_id!==(int)config('app.master_company_id',1)))return;
            $legacy=\App\Services\SaaS\SubscriptionFeatures::differences($previous->features??[],$previous->plan?->features??[]);
            unset($legacy['store_policy']);
            $subscription->feature_overrides=array_replace_recursive($legacy,$previous->feature_overrides??[]);
        });
        static::saved(function(self $subscription) {
            if(!\Illuminate\Support\Facades\Schema::hasTable('store_seller_profiles'))return;
            if(!in_array($subscription->status,['active','trial'],true)||!($subscription->wasRecentlyCreated||$subscription->wasChanged(['status','plan_id'])))return;
            $previous=self::withoutGlobalScopes()->where('company_id',$subscription->company_id)->where('id','<',$subscription->id)->whereNull('deleted_at')->latest('id')->first();
            if($previous&&(int)$previous->plan_id!==(int)$subscription->plan_id) {
                $profile=\Modules\Store\Models\StoreSellerProfile::where('company_id',$subscription->company_id)->first();
                if($profile&&$profile->policy_overrides)$profile->update(['policy_review_required'=>true]);
            }
        });
    }
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
        'feature_overrides' => 'array',
        'paid_at' => 'datetime',
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
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
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
        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction) {
            $locked = self::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->id);
            if (!$locked->paid_at) $locked->applyPaidActivation($transaction);
        }, 3);
        $this->refresh();
    }

    protected function applyPaidActivation($transaction = null)
    {
        if ($this->paid_at) return;
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
            'paid_at' => now(),
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

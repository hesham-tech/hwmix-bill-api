<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\FilterableByCompany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\Pivot; // تم التغيير من Model إلى Pivot
use App\Observers\CompanyUserObserver;

// تم إزالة Traits مثل HasRoles, HasPermissions, HasImages, LogsActivity 

#[ObservedBy([CompanyUserObserver::class])]
class CompanyUser extends Pivot
{
    // تم الإبقاء على HasFactory فقط من الـ Traits الأساسية للنماذج
    use HasFactory, FilterableByCompany, \App\Traits\SmartSearch;

    protected $table = 'company_user';

    protected $guarded = [];

    protected $casts = [
        'sales_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($pivot) {
            if (!$pivot->user_id)
                return;

            $user = User::query()->withoutGlobalScopes()->find($pivot->user_id);
            if (!$user)
                return;

            // Fallback for nickname
            if (empty($pivot->nickname_in_company)) {
                $pivot->nickname_in_company = $user->nickname ?? $user->username;
            }

            // Fallback for full name
            if (empty($pivot->full_name_in_company)) {
                $pivot->full_name_in_company = $user->full_name;
            }

            // Set creator if not set
            if (empty($pivot->created_by)) {
                $pivot->created_by = auth()->id() ?? $user->id;
            }
        });
    }

    /**
     * العلاقات التي يجب تحميلها تلقائيا عند جلب الموديل.
     * تم إزالتها لمنع التكرار اللا نهائي (Infinite Recursion) عند تسلسل بيانات المستخدم.
     */
    // protected $with = [
    //     'user',
    //     'company'
    // ];


    /**
     * الحصول على المستخدم المرتبط بسجل الشركة.
     */
    public function user(): BelongsTo
    {
        // تم التأكيد على المفتاح الخارجي إذا لم يكن قياسياً (لكن هنا قياسي)
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * الحصول على الشركة المرتبطة بسجل المستخدم.
     */
    public function company(): BelongsTo
    {
        // تم التأكيد على المفتاح الخارجي إذا لم يكن قياسياً (لكن هنا قياسي)
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * الحصول على من قام بإنشاء هذا السجل في company_user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * علاقة مباشرة للحصول على الخزنة الافتراضية للمستخدم في هذه الشركة
     * * @return HasOne
     */
    public function defaultCashBox(): HasOne
    {
        // تم تبسيط علاقة HasOneThrough هنا لتكون أكثر ملاءمة للـ Pivot
        // باستخدام حقل user_id من الـ Pivot كـ local key
        return $this->hasOne(CashBox::class, 'user_id', 'user_id')
            ->where('is_default', true)
            ->where('company_id', $this->company_id);
    }

    /**
     * الحصول على الاسم الكامل للمستخدم في هذه الشركة (مع ارتداد للاسم العالمي)
     */
    public function getFullNameAttribute()
    {
        return $this->full_name_in_company ?? $this->user?->full_name;
    }

    /**
     * الحصول على اللقب للمستخدم في هذه الشركة (مع ارتداد للقب العالمي)
     */
    public function getNicknameAttribute()
    {
        return $this->nickname_in_company ?? $this->user?->nickname;
    }

    /**
     * الحصول على الرصيد الفعلي للمستخدم في هذه الشركة
     */
    public function getBalanceAttribute()
    {
        return (float) ($this->defaultCashBox?->balance ?? 0);
    }

    /**
     * الحصول على نوع العميل في هذه الشركة
     */
    public function getCustomerTypeAttribute()
    {
        return $this->customer_type_in_company ?? 'retail';
    }

    /**
     * الحصول على الاسم المفضل (لقب ثم اسم كامل)
     */
    public function getNameAttribute()
    {
        return $this->nickname_in_company ?: ($this->full_name_in_company ?: ($this->user?->nickname ?: $this->user?->full_name));
    }

    /**
     * الحصول على رقم الهاتف للتواصل في هذه الشركة (مع ارتداد للرقم العالمي)
     */
    public function getPhoneAttribute()
    {
        return $this->user?->phone;
    }

    /**
     * الحصول على البريد الإلكتروني للتواصل في هذه الشركة (مع ارتداد للبريد العالمي)
     */
    public function getEmailAttribute()
    {
        return $this->user?->email;
    }

    /**
     * الحصول على جميع صناديق النقد للمستخدم في هذه الشركة
     * * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxesAttribute()
    {
        if (!$this->relationLoaded('user') || !$this->user) {
            return collect();
        }

        return collect($this->user->cashBoxes ?? [])
            ->where('company_id', $this->company_id);
    }

    /**
     * الحصول على الخزنة الافتراضية للمستخدم في هذه الشركة
     * * @return CashBox|null
     */
    public function getDefaultCashBoxAttribute()
    {
        if (!$this->relationLoaded('user') || !$this->user) {
            return null;
        }

        return collect($this->user->cashBoxes ?? [])
            ->where('company_id', $this->company_id)
            ->where('is_default', true)
            ->first();
    }
}
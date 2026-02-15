<?php

namespace App\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Services\CashBoxService;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\ManagesBalance;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\UserObserver;
// يجب استيراد النماذج (Models) المستخدمة داخل الكود:
use App\Models\CashBox;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Translation; // تم استخدامه في دالة trans
use App\Models\RoleCompany; // تم استخدامه في دالة createdRoles


/**
 * @method void deposit(float|int $amount)
 */
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasApiTokens, Filterable, Scopes, LogsActivity, HasImages, ManagesBalance, \App\Traits\FilterableByCompany, \App\Traits\SmartSearch;
    use HasRoles, HasPermissions {
        HasPermissions::hasPermissionTo insteadof HasRoles;
        HasPermissions::hasPermissionTo as traitHasPermissionTo;
    }


    /**
     * الحقول التي لا تخضع لـ Mass Assignment Protection.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * الحقول التي يجب إخفاؤها عند التحويل إلى مصفوفة/JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url', 'name'];

    /**
     * تعريف أنواع البيانات للمحولات (Casts).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * الإجراءات التي يتم تنفيذها بعد تمهيد النموذج (مثل إنشاء صندوق نقد افتراضي عند إنشاء مستخدم جديد).
     */
    protected static function booted(): void
    {
        /**
         * @see \App\Observers\UserObserver
         * يتم معالجة مزامنة البيانات (Sync) وتسجيل النشاطات (ActivityLog) عبر المراقب (Observer)
         */

        static::created(function ($user) {
            // [تمت الإزالة]: يعتمد النظام الآن على CompanyUserObserver لإنشاء الخزنة عند الربط
        });
    }

    /**
     * علاقة MorphMany للوصول إلى ترجمات النموذج.
     */
    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }


    /**
     * الحصول على الخزنة الافتراضية لشركة محددة أو للشركة النشطة للمستخدم الموثق.
     *
     * @param int|null $companyId
     * @return CashBox|null
     */
    public function getDefaultCashBoxForCompany($companyId = null)
    {
        // الأولوية لـ Auth::user()->company_id لضمان جلب رصيد الشركة النشطة حالياً حتى لو كان المستخدم في شركة أخرى
        $companyId = $companyId ?? Auth::user()->company_id ?? $this->company_id ?? null;

        if (!$companyId) {
            return null;
        }

        $cashBox = $this->cashBoxes()
            ->where('is_default', true)
            ->where('company_id', $companyId)
            ->first();

        // إذا لم يتم العثور على خزنة افتراضية، نحاول البحث عن أي خزنة نشطة
        if (!$cashBox) {
            $cashBox = $this->cashBoxes()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        // إذا لم يوجد، نقوم بإنشاء واحدة تلقائياً للمستخدم في هذه الشركة لضمان استمرارية العمليات المالية
        if (!$cashBox && $companyId) {
            try {
                $cashBox = app(CashBoxService::class)->createDefaultCashBoxForUserCompany(
                    $this->id,
                    $companyId,
                    Auth::id() ?? $this->id
                );
            } catch (Exception $e) {
                Log::error("فشل إنشاء خزنة تلقائية للمستخدم {$this->id} في الشركة {$companyId}: " . $e->getMessage());
            }
        }

        return $cashBox;
    }

    /**
     * نطاق استعلام (Scope) لتحميل المستخدمين مع خزنتهم الافتراضية لشركة معينة.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $companyId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDefaultCashBox($query, $companyId = null)
    {
        $companyId = $companyId ?? Auth::user()->company_id ?? null;

        return $query->with([
            'cashBoxes' => function ($q) use ($companyId) {
                $q->where('is_default', true);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            }
        ]);
    }

    /**
     * الشركة النشطة حالياً للمستخدم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * علاقة BelongsToMany بين المستخدم والشركات التي يعمل بها.
     */
    public function companies(): BelongsToMany
    {
        return $this
            ->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
            ->using(CompanyUser::class)
            ->withTimestamps()
            ->withPivot([
                'nickname_in_company',
                'full_name_in_company',
                'position_in_company',
                'balance_in_company',
                'customer_type_in_company',
                'status',
                'created_by'
            ]);
    }

    /**
     * علاقة HasMany للحصول على جميع سجلات المستخدم في جدول company_user.
     */
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    /**
     * علاقة HasOne للحصول على سجل المستخدم الحالي في جدول company_user للشركة النشطة.
     */
    public function activeCompanyUser(): HasOne
    {
        $activeCompanyId = $this->company_id ?? null;

        return $this->hasOne(CompanyUser::class, 'user_id')
            ->where('company_id', $activeCompanyId);
    }



    /**
     * علاقة HasMany للحصول على جميع صناديق النقد (Cash Boxes) الخاصة بالمستخدم.
     */
    public function cashBoxes(): HasMany
    {
        return $this->hasMany(CashBox::class, 'user_id');
    }

    /**
     * Groups this user belongs to.
     */
    public function taskGroups(): BelongsToMany
    {
        return $this->belongsToMany(TaskGroup::class, 'task_group_user');
    }

    /**
     * الحصول على صناديق النقد الخاصة بالمستخدم ضمن شركة معينة.
     *
     * @param int|null $companyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxesForCompany($companyId = null)
    {
        $companyId = $companyId ?? $this->company_id ?? Auth::user()->company_id ?? null;

        if (!$companyId) {
            return collect();
        }

        return $this->cashBoxes()->where('company_id', $companyId)->get();
    }


    /**
     * الحصول على رصيد صندوق نقدية محدد أو صندوق النقدية الافتراضي للشركة النشطة.
     *
     * @param int|null $id معرف صندوق النقدية.
     * @return float
     */
    public function balanceBox($id = null): float
    {
        $cashBox = null;
        if ($id) {
            $cashBox = $this->cashBoxes()->where('id', $id)->first();
        } else {
            // جلب الخزنة الافتراضية للشركة الحالية (بناءً على جلسة العمل أو إعدادات المستخدم)
            $cashBox = $this->getDefaultCashBoxForCompany();
        }
        return $cashBox ? (float) $cashBox->balance : 0.0;
    }

    /**
     * الحصول على صناديق النقد للمستخدم في الشركة النشطة (استدعاء لـ getCashBoxesForCompany).
     */

    /**
     * علاقة للحصول على الأدوار التي أنشأها المستخدم (علاقة HasManyThrough مع جدول RoleCompany).
     */
    public function createdRoles()
    {
        return $this->hasManyThrough(
            Role::class,
            RoleCompany::class,
            'created_by',
            'id',
            'id',
            'role_id'
        );
    }

    /**
     * علاقة HasMany للحصول على الأقساط الخاصة بهذا المستخدم (العميل).
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'user_id');
    }

    /**
     * علاقة HasMany للحصول على الأقساط التي أنشأها هذا المستخدم.
     */
    public function createdInstallments(): HasMany
    {
        return $this->hasMany(Installment::class, 'created_by');
    }

    /**
     * الحصول على أدوار المستخدم مع قائمة أذونات كل دور.
     */
    public function getRolesWithPermissions()
    {
        return $this->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });
    }

    /**
     * علاقة HasMany للحصول على جميع المعاملات المالية الخاصة بالمستخدم.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    /**
     * علاقة BelongsTo للحصول على المستخدم الذي أنشأ هذا المستخدم.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * علاقة HasMany للحصول على جميع المدفوعات التي قام بها هذا المستخدم.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }


    /**
     * علاقة HasMany للحصول على الفواتير الخاصة بالمستخدم.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * علاقة HasMany للحصول على خطط التقسيط الخاصة بالمستخدم.
     */
    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    /**
     * إرجاع جميع معرفات المستخدمين التابعين للمستخدم الحالي بشكل تسلسلي داخل الشركة النشطة.
     *
     * @return array
     */
    public function getDescendantUserIds(): array
    {
        // يتطلب استيراد CompanyUser
        $companyId = Auth::user()->company_id ?? null;

        if (is_null($companyId)) {
            return [];
        }

        $descendants = [];
        $stack = [$this->id];

        while (!empty($stack)) {
            $parentId = array_pop($stack);

            $children = CompanyUser::where('created_by', $parentId)
                ->where('company_id', $companyId)
                ->pluck('user_id')
                ->toArray();

            foreach ($children as $childUserId) {
                if (!in_array($childUserId, $descendants)) {
                    $descendants[] = $childUserId;
                    $stack[] = $childUserId;
                }
            }
        }

        if (($key = array_search($this->id, $descendants)) !== false) {
            unset($descendants[$key]);
        }
        return array_values($descendants);
    }



    /**
     * إرجاع الشركات المرئية للمستخدم بناءً على صلاحياته (جميع الشركات للسوبر أدمن أو الشركات المرتبط بها).
     */
    public function getVisibleCompaniesForUser()
    {
        // يتطلب استيراد Company
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return Company::all();
        }
        // استخدام withoutGlobalScopes لرؤية جميع الشركات المرتبط بها المستخدم
        return $this->companies()->withoutGlobalScopes()->get();
    }

    /**
     * يتحقق مما إذا كان المستخدم (كعميل/موظف) لديه أي سجلات حركية/مالية مرتبطة بالشركة المحددة.
     * @param int $companyId معرف الشركة النشطة
     * @return bool
     */
    /**
     * يتحقق مما إذا كان المستخدم (كعميل/موظف) لديه أي سجلات حركية/مالية مرتبطة بالشركة المحددة.
     *
     * @param int $companyId معرف الشركة النشطة
     * @return array|null مصفوفة تحتوي على سبب المنع (الرسالة)، أو null إذا كان الحذف آمنًا.
     */
    public function hasActiveTransactionsInCompany(int $companyId): ?array
    {
        // 1. فحص الفواتير (Invoices)
        if ($this->invoices()->where('company_id', $companyId)->exists()) {
            return ['message' => 'لا يمكن فصل العميل لوجود فواتير  مسجلة باسمه في هذه الشركة.'];
        }

        // 2. فحص المعاملات المالية (Transactions)
        if ($this->transactions()->where('company_id', $companyId)->exists()) {
            return ['message' => 'لا يمكن فصل العميل لوجود سجلات معاملات مالية مرتبطة به في هذه الشركة.'];
        }

        // 3. فحص المدفوعات (Payments)
        if ($this->payments()->where('company_id', $companyId)->exists()) {
            return ['message' => 'لا يمكن فصل العميل لوجود سجلات مدفوعات  قام بها في هذه الشركة.'];
        }

        // 4. فحص الأقساط (Installments)
        if ($this->installments()->where('company_id', $companyId)->exists()) {
            return ['message' => 'لا يمكن فصل العميل لوجود أقساط  مستحقة أو مدفوعة مرتبطة به في هذه الشركة.'];
        }

        // 5. فحص خطط التقسيط (Installment Plans)
        if ($this->installmentPlans()->where('company_id', $companyId)->exists()) {
            return ['message' => 'لا يمكن فصل العميل لوجود خطط تقسيط مسجلة باسمه في هذه الشركة.'];
        }

        // 6. فحص رصيد الخزنة: إذا كان المستخدم يمتلك خزنة في هذه الشركة ورصيدها ليس صفرًا
        if ($this->cashBoxes()->where('company_id', $companyId)->where('balance', '!=', 0)->exists()) {
            return ['message' => 'لا يمكن فصل المستخدم لوجود رصيد متبقي غير صفري في خزنته الافتراضية لهذه الشركة.'];
        }

        // إذا لم يتم العثور على أي سجلات حركية/مالية، يكون الحذف آمنًا
        return null;
    }



    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "المستخدم ({$this->nickname})";
    }

    /**
     * Get the user's avatar URL.
     */
    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute()
    {
        return $this->image?->url ? asset($this->image->url) : null;
    }

    /**
     * Override hasPermissionTo to handle 'admin.super' globally.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $superAdminKey = perm_key('admin.super');

        // Check for super admin permission in any context (team-blind)
        // This is necessary because super admin should have global access
        // even if the permission is seeded within a specific company.
        if ($permission === $superAdminKey || (is_object($permission) && $permission->name === $superAdminKey)) {
            static $isSuperAdmin = [];
            if (!isset($isSuperAdmin[$this->id])) {
                $isSuperAdmin[$this->id] = \DB::table('model_has_permissions')
                    ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('model_id', $this->id)
                    ->where('model_type', get_class($this))
                    ->where('permissions.name', $superAdminKey)
                    ->exists();
            }
            return $isSuperAdmin[$this->id];
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

    /**
     * الحصول على الاسم الكامل بشكل مرن (Accessor)
     */
    public function getNameAttribute()
    {
        // استخدام العلاقة فقط إذا كانت محملة لتجنب التكرار اللانهائي أثناء التسلسل
        $activeCompanyUser = $this->relationLoaded('activeCompanyUser') ? $this->activeCompanyUser : null;

        // 1. الأولوية للقب في الشركة المحددة
        if ($activeCompanyUser && !empty($activeCompanyUser->nickname_in_company)) {
            return $activeCompanyUser->nickname_in_company;
        }

        // 2. الاسم الكامل في الشركة المحددة
        if ($activeCompanyUser && !empty($activeCompanyUser->full_name_in_company)) {
            return $activeCompanyUser->full_name_in_company;
        }

        // 3. اللقب العالمي
        if (!empty($this->nickname))
            return $this->nickname;

        // 4. الاسم الكامل العالمي
        if (!empty($this->full_name))
            return $this->full_name;

        // 5. المحاولة باسم المستخدم
        if (!empty($this->username))
            return $this->username;

        return 'عميل غير معروف';
    }

    /**
     * الحصول على اللقب (Context-Aware)
     */
    public function getNicknameAttribute($value)
    {
        return $this->activeCompanyUser?->nickname_in_company ?? $value;
    }

    /**
     * الحصول على الاسم الكامل (Context-Aware)
     */
    public function getFullNameAttribute($value)
    {
        return $this->activeCompanyUser?->full_name_in_company ?? $value;
    }

    /**
     * الحصول على الرصيد (المصدر الوحيد: الخزنة)
     * تم تحسينه ليدعم التحميل المسبق (Eager Loading) وتجنب N+1 queries
     */
    public function getBalanceAttribute()
    {
        // إذا كانت العلاقة محملة مسبقاً، نستخدم المجموعة (Collection) لتوفير الاستعلامات
        if ($this->relationLoaded('cashBoxes')) {
            $activeCompanyId = Auth::user()->company_id ?? $this->company_id;

            // البحث عن الخزنة الافتراضية أولاً، ثم أي خزنة نشطة في الشركة المحددة
            $cashBox = $this->cashBoxes
                ->where('company_id', $activeCompanyId)
                ->where('is_default', true)
                ->first()
                ?? $this->cashBoxes
                    ->where('company_id', $activeCompanyId)
                    ->where('is_active', true)
                    ->first();

            return $cashBox ? (float) $cashBox->balance : 0.0;
        }

        // في حال لم تكن محملة، نعود للمنطق الافتراضي الذي ينفذ استعلاماً مستقلاً
        return (float) $this->balanceBox();
    }

    /**
     * الحصول على المسمى الوظيفي (Context-Aware)
     */
    public function getPositionAttribute($value)
    {
        return $this->activeCompanyUser?->position_in_company ?? $value;
    }

    /**
     * الحصول على نوع العميل (Context-Aware)
     */
    public function getCustomerTypeAttribute($value)
    {
        return $this->activeCompanyUser?->customer_type_in_company ?? $value;
    }

}

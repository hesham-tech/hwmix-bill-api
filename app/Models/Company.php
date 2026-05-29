<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Traits\Translations\Translatable;
use App\Traits\Filterable;
use App\Traits\HasImages;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Observers\CompanyObserver;

// #[ScopedBy([CompanyScope::class])]
/**
 * تعليق عربي: كلاس الشركة للنظام ويمثل المستأجر (Tenant) الأساسي في بيئة الـ Multi-Tenant.
 */
#[ObservedBy([CompanyObserver::class])]
class Company extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, Filterable, Scopes, RolePermissions, LogsActivity, HasImages;

    /**
     * تجاوز الـ Global Scopes عند ربط النموذج بالـ Route (Route Model Binding).
     * هذا يمنع خطأ 404 بسبب فلتر الشركة النشطة عند الوصول لشركة مختلفة.
     * التحقق من الصلاحيات يتم داخل الـ Controller.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'name',
        'description',
        'field',
        'owner_name',
        'address',
        'phone',
        'email',
        'tax_number',
        'website',
        'social_links',
        'settings',
        'created_by',
        'company_id',
        'default_cash_customer_id',
    ];

    protected $casts = [
        'social_links' => 'array',
        'settings' => 'array',
    ];

    // Define the many-to-many relationship
    protected $dates = [
        'created_at',
        'updated_at',
    ];
    // يجيب المستخدمين مباشرة (Many To Many)
    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'company_user', 'company_id', 'user_id')
            ->using(CompanyUser::class)
            ->withTimestamps()
            ->withPivot([
                'nickname_in_company',
                'full_name_in_company',
                'position_in_company',
                'customer_type_in_company',
                'status',
                'user_phone',
                'user_email',
                'user_username',
                'created_by'
            ]);
    }

    // يجيب العضويات (Pivot Model)
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }



    public function userCompanyCash()
    {
        return $this
            ->belongsToMany(User::class, 'user_company_cash')
            ->withPivot('cash_box_id', 'created_by');  // أضف الحقول الإضافية التي تريد الوصول إليها
    }

    /**
     * علاقة الشركة بالفروع
     */
    public function branches()
    {
        return $this->hasMany(\Modules\Companies\Models\Branch::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع صناديق النقدية
    public function cashBoxes(): BelongsToMany
    {
        return $this
            ->belongsToMany(CashBox::class, 'user_company_cash')
            ->withPivot('user_id')  // إذا كنت بحاجة إلى الوصول إلى user_id
            ->withTimestamps();  // إذا كنت بحاجة إلى الوصول إلى timestamps
    }

    public function logo()
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'logo');
    }

    /**
     * العلاقة مع أنواع الفواتير عبر جدول الربط company_invoice_type
     */
    public function invoiceTypes(): BelongsToMany
    {
        return $this->belongsToMany(InvoiceType::class, 'company_invoice_type')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * أنواع الفواتير النشطة فقط لهذه الشركة
     */
    public function activeInvoiceTypes(): BelongsToMany
    {
        return $this->invoiceTypes()->wherePivot('is_active', true);
    }

    /**
     * الحصول على إعدادات الطباعة من حقل settings مع قيم افتراضية
     */
    public function getPrintSettingsAttribute(): array
    {
        $defaults = [
            'print_format' => 'thermal', // thermal, a4, a5
            'show_logo' => true,
            'header_text' => '',
            'footer_text' => 'شكراً لتعاملكم معنا',
            'thermal_width' => '80mm',
        ];

        return array_merge($defaults, $this->settings['print_settings'] ?? []);
    }

    /**
     * الحصول على طريقة التقييم المالي للمخزون
     */
    public function getInventoryValuationMethodAttribute(): string
    {
        return $this->settings['inventory_valuation_method'] ?? 'average'; // average, fifo, last_purchase_price
    }

    /**
     * الحصول على حالة التحديث التلقائي لسعر الشراء
     */
    public function getAutoUpdatePurchasePriceAttribute(): bool
    {
        return (bool)($this->settings['auto_update_purchase_price'] ?? true);
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "الشركة ({$this->name})";
    }

    /**
     * علاقة العميل النقدي الافتراضي للشركة.
     */
    public function defaultCashCustomer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_cash_customer_id');
    }

    /**
     * الحصول على العميل النقدي أو إنشاؤه تلقائياً إذا كان مفقوداً أو محذوفاً.
     */
    public function getOrCreateDefaultCashCustomer()
    {
        if ($this->default_cash_customer_id) {
            $customer = User::withoutGlobalScopes()->find($this->default_cash_customer_id);
            if ($customer) {
                return $customer;
            }
        }

        return \DB::transaction(function () {
            $phone = '999' . str_pad($this->id, 7, '0', STR_PAD_LEFT);
            $email = "cash.customer.{$this->id}@hwnix.local";
            $username = "cash_customer_{$this->id}";

            $customer = User::withoutGlobalScopes()->where('phone', $phone)->first();

            if (!$customer) {
                $customer = User::create([
                    'phone' => $phone,
                    'email' => $email,
                    'full_name' => 'عميل نقدي',
                    'nickname' => 'عميل نقدي',
                    'password' => \Hash::make('cash_customer_secret'),
                    'username' => $username,
                    'created_by' => $this->created_by ?? 1,
                    'active_company_id' => $this->id,
                    'status' => 'active',
                ]);
            }

            $pivotExists = CompanyUser::where('user_id', $customer->id)
                ->where('company_id', $this->id)
                ->exists();

            if (!$pivotExists) {
                CompanyUser::create([
                    'user_id' => $customer->id,
                    'company_id' => $this->id,
                    'nickname_in_company' => 'عميل نقدي',
                    'full_name_in_company' => 'عميل نقدي',
                    'customer_type_in_company' => 'cash_customer',
                    'status' => 'active',
                    'created_by' => $this->created_by ?? 1,
                ]);
            }

            $defaultBranchId = $this->branches()->where('is_default', true)->value('id');
            if ($defaultBranchId) {
                $branchUserExists = \DB::table('branch_user')
                    ->where('user_id', $customer->id)
                    ->where('branch_id', $defaultBranchId)
                    ->exists();

                if (!$branchUserExists) {
                    \DB::table('branch_user')->insert([
                        'user_id' => $customer->id,
                        'branch_id' => $defaultBranchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->update(['default_cash_customer_id' => $customer->id]);

            return $customer;
        });
    }
}

<?php

namespace App\Models;

use App\Models\User;
use App\Traits\Scopes;
use App\Models\Company;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Blameable;
use App\Traits\FilterableByBranch;
use App\Models\Scopes\CompanyScope;
use App\Enums\CashBoxStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[ScopedBy([CompanyScope::class])]
/**
 * موديل الصندوق المالي (CashBox) - التطبيق الرئيسي
 */
class CashBox extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable, FilterableByBranch;

    protected static function booted()
    {
        // منع الحذف المادي نهائياً لضمان سلامة الأثر المالي التاريخي
        static::deleting(function ($cashBox) {
            throw new \Exception('لا يمكن حذف الخزائن نهائياً من النظام لضمان النزاهة التاريخية للعمليات المالية. يرجى تعطيل الخزنة بدلاً من ذلك.');
        });

        static::creating(function ($cashBox) {
            // توليد رمز Identity فريد تلقائياً CBX-000000
            if (is_null($cashBox->code)) {
                $latest = static::withoutGlobalScopes()->orderBy('id', 'desc')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $cashBox->code = 'CBX-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            }
        });

        static::updating(function ($cashBox) {
            // منع تعديل الشركة والفرع بعد الإنشاء لضمان الاستقرار المعماري
            if ($cashBox->isDirty('company_id')) {
                throw new \Exception('لا يمكن تعديل الشركة المرتبطة بالخزنة الماليّة بعد إنشائها لضمان سلامة القيود التاريخية.');
            }
            if ($cashBox->isDirty('branch_id')) {
                throw new \Exception('لا يمكن تعديل الفرع المرتبط بالخزنة الماليّة بعد إنشائها لضمان سلامة القيود التاريخية.');
            }

            // فحص الـ Backtrace لمنع تعديل الرصيد بشكل مباشر
            if ($cashBox->isDirty('balance')) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                $isAllowed = false;
                foreach ($backtrace as $trace) {
                    if (isset($trace['class']) && in_array($trace['class'], [\App\Services\CashService::class, \Database\Seeders\CompanyDataSyncSeeder::class])) {
                        $isAllowed = true;
                        break;
                    }
                }
                if (!$isAllowed) {
                    throw new \Exception('ممنوع تعديل رصيد الخزنة مباشرة. يجب إجراء كافة التعديلات عبر المحرك المالي FinancialEngine.');
                }
            }
        });

        static::created(function ($cashBox) {
            if ($cashBox->balance != 0 && !\App\Models\Transaction::$preventObserverLog) {
                \App\Models\Transaction::create([
                    'user_id' => $cashBox->user_id,
                    'cashbox_id' => $cashBox->id,
                    'created_by' => \Illuminate\Support\Facades\Auth::id() ?? $cashBox->user_id,
                    'company_id' => $cashBox->company_id,
                    'type' => 'deposit',
                    'amount' => abs((float)$cashBox->balance),
                    'balance_before' => 0,
                    'balance_after' => (float)$cashBox->balance,
                    'description' => 'رصيد افتتاحي للخزينة عند الإنشاء',
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'balance',
        'code',
        'status',
        'is_active',
        'is_default',
        'cash_box_type_id',
        'user_id',
        'created_by',
        'company_id',
        'branch_id',
        'description',
        'account_number',
        'access_type',
    ];

    protected $casts = [
        'status' => CashBoxStatus::class,
    ];

    /**
     * متوافق للخلف: محاكاة حقل النشاط كحقل وهمي مشتق من حالة آلة الحالات
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === CashBoxStatus::ACTIVE;
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['status'] = $value ? CashBoxStatus::ACTIVE->value : CashBoxStatus::INACTIVE->value;
    }

    /**
     * متوافق للخلف: محاكاة حقل الافتراضية كحقل وهمي مشتق من جدول المستخدمين
     */
    public function getIsDefaultAttribute(): bool
    {
        $user = auth()->user();
        if ($user) {
            $defaultBox = $user->getDefaultCashBoxForCompany($this->company_id, $this->branch_id);
            if ($defaultBox && $defaultBox->id === $this->id) {
                return true;
            }
        }
        return (bool)($this->attributes['is_default'] ?? false);
    }

    /**
     * Scopes المشتركة
     */
    public function scopeActive($query)
    {
        return $query->where('status', CashBoxStatus::ACTIVE);
    }

    public function scopeUsable($query)
    {
        return $query->where('status', CashBoxStatus::ACTIVE)
            ->where('access_type', '!=', 'legacy_archived');
    }

    /**
     * العلاقة مع الفرع التابع للشركة النشطة
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Companies\Models\Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeBox(): BelongsTo
    {
        return $this->belongsTo(CashBoxType::class, 'cash_box_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المستخدمون المصرح لهم بالوصول إلى الخزنة المشتركة
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cash_box_user', 'cash_box_id', 'user_id')
            ->withPivot('created_by')
            ->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function logLabel()
    {
        return "الصندوق ({$this->name})";
    }
}

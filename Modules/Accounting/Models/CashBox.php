<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use App\Traits\Scopes;
use App\Models\Company;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Blameable;
use App\Traits\FilterableByBranch;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[ScopedBy([CompanyScope::class])]
/**
 * موديل الصندوق المالي (CashBox) - موديول المحاسبة
 */
class CashBox extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable, FilterableByBranch;

    protected static function booted()
    {
        static::saving(function ($cashBox) {
            if ($cashBox->is_default) {
                static::withoutGlobalScopes()
                    ->where('user_id', $cashBox->user_id)
                    ->where('company_id', $cashBox->company_id)
                    ->where('branch_id', $cashBox->branch_id)
                    ->where('cash_box_type_id', $cashBox->cash_box_type_id)
                    ->where('id', '!=', $cashBox->id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($cashBox) {
            if ($cashBox->isDirty('branch_id')) {
                throw new \Exception('لا يمكن تعديل الفرع المرتبط بالخزنة الماليّة بعد إنشائها لضمان سلامة القيود التاريخية.');
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
        'is_default',
        'is_active',
        'cash_box_type_id',
        'user_id',
        'created_by',
        'company_id',
        'branch_id',
        'description',
        'account_number',
    ];

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function logLabel()
    {
        return "الصندوق ({$this->name})";
    }
}

<?php

namespace App\Models;

use App\Models\User;
use App\Traits\Scopes;
use App\Models\Company;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Blameable;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

#[ScopedBy([CompanyScope::class])]
/**
 * @mixin IdeHelperCashBox
 */
class CashBox extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable;

    protected static function booted()
    {
        static::saving(function ($cashBox) {
            if ($cashBox->is_default) {
                static::where('user_id', $cashBox->user_id)
                    ->where('company_id', $cashBox->company_id)
                    ->where('id', '!=', $cashBox->id)
                    ->update(['is_default' => false]);
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
        'description',
        'account_number',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function typeBox(): BelongsTo
    {
        return $this->belongsTo(CashBoxType::class, 'cash_box_type_id');
    }
    // العلاقة مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // العلاقة مع الشركات
    public function company(): belongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

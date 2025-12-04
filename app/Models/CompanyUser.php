<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyUser extends Model
{
    use HasFactory, Translatable, HasRoles, Filterable, Scopes, HasPermissions, LogsActivity, HasImages;

    protected $table = 'company_user';

    protected $guarded = [];

    /**
     * العلاقات التي يجب تحميلها تلقائيا عند جلب الموديل.
     *
     * @var array
     */
    protected $with = [
        'user',
        'company'
    ];


    /**
     * الحصول على المستخدم المرتبط بسجل الشركة.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الحصول على الشركة المرتبطة بسجل المستخدم.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
     * 
     * @return HasOne
     */
    public function defaultCashBox(): HasOne
    {
        return $this->hasOneThrough(
            CashBox::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id'
        )
            ->where('cash_boxes.is_default', true)
            ->where('cash_boxes.company_id', $this->company_id);
    }

    /**
     * الحصول على جميع صناديق النقد للمستخدم في هذه الشركة
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCashBoxesAttribute()
    {
        if (!$this->relationLoaded('user')) {
            return collect();
        }

        return $this->user->cashBoxes
            ->where('company_id', $this->company_id);
    }

    /**
     * الحصول على الخزنة الافتراضية للمستخدم في هذه الشركة
     * 
     * @return CashBox|null
     */
    public function getDefaultCashBoxAttribute()
    {
        if (!$this->relationLoaded('user')) {
            return null;
        }

        return $this->user->cashBoxes
            ->where('company_id', $this->company_id)
            ->where('is_default', true)
            ->first();
    }
}

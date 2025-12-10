<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot; // تم التغيير من Model إلى Pivot

// تم إزالة Traits مثل HasRoles, HasPermissions, HasImages, LogsActivity 

class CompanyUser extends Pivot
{
    // تم الإبقاء على HasFactory فقط من الـ Traits الأساسية للنماذج
    use HasFactory; 

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
     * الحصول على جميع صناديق النقد للمستخدم في هذه الشركة
     * * @return \Illuminate\Database\Eloquent\Collection
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
     * * @return CashBox|null
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
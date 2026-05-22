<?php

namespace Modules\Guidance\Models;

/**
 * كلاس تتبع تقدم إرشادات المستخدم والموديل الخاص بها لدعم الـ Multi-Tenant.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([CompanyScope::class])]
class UserGuidanceProgress extends Model
{
    use HasFactory, Blameable;

    /**
     * اسم الجدول المرتبط بالموديل
     *
     * @var string
     */
    protected $table = 'user_guidance_progress';

    /**
     * الحقول القابلة للتعبئة جماعياً
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'key',
        'completed_at',
        'skipped',
        'created_by',
    ];

    /**
     * تحويل أنواع الحقول
     *
     * @var array
     */
    protected $casts = [
        'completed_at' => 'datetime',
        'skipped' => 'boolean',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * العلاقة مع المستخدم
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * العلاقة مع الشركة
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /**
     * العلاقة مع منشئ السجل
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}


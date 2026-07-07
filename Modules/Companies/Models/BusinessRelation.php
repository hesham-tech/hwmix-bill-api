<?php

namespace Modules\Companies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Company;

/**
 * يمثل العلاقات التجارية والوظائف المتعددة للطرف (عميل، موظف، مورد، شريك) داخل الشركة.
 */
class BusinessRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'relation_type',
        'metadata',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع الشركة الأم.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * العلاقة مع المستخدم (الطرف).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع منشئ العلاقة.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

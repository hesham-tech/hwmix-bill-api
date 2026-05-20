<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نموذج حفظ تفضيلات الجداول وواجهة المستخدم المخصصة لكل مستخدم وشركة.
 */
class UserTablePreference extends Model
{
    protected $table = 'user_table_preferences';

    protected $fillable = [
        'user_id',
        'company_id',
        'table_key',
        'preferences',
        'created_by',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    /**
     * علاقة التفضيلات بالمستخدم.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة التفضيلات بالشركة.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

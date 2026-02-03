<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;  // افترض أن هذا trait مخصص ومطلوب
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;  // إضافة هذه الواجهة للعلاقة ManyToMany
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @mixin IdeHelperRole
 */
class Role extends SpatieRole implements RoleContract
{
    // HasRoles و HasPermissions متوفرتان بالفعل من SpatieRole، لذا لا حاجة لتكرارهما هنا.
    // افترض أن Scopes و LogsActivity و RolePermissions traits مخصصة ومطلوبة.
    use Scopes, Blameable, LogsActivity, RolePermissions;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "دور ({$this->name})";
    }

    protected $fillable = [
        'name',
        'guard_name',
        'created_by',
        'company_id',
        'label',
        'description'
    ];

    /**
     * العلاقة التي تحدد المستخدم الذي أنشأ هذا الدور.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة التي تحدد الشركة التي ينتمي إليها هذا الدور.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // يمكن إضافة دوال أو منطق إضافي هنا إذا لزم الأمر
}

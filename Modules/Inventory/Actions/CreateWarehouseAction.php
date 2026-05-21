<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * أكشن إنشاء مستودع جديد
 */
class CreateWarehouseAction extends BaseAction
{
    /**
     * تنفيذ عملية الإنشاء
     */
    public function handle(array $data = []): Warehouse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        
        // التحقق من الصلاحيات
        if (!$authUser->hasPermissionTo(perm_key('admin.super')) && 
            !$authUser->hasPermissionTo(perm_key('warehouses.create')) && 
            !$authUser->hasPermissionTo(perm_key('admin.company'))) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لإنشاء مستودعات.");
        }

        return DB::transaction(function () use ($data, $authUser) {
            $companyId = $authUser->active_company_id;
            
            // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمتخدم.
            $warehouseCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($data['company_id']))
                ? $data['company_id']
                : $companyId;

            // التأكد من أن المستخدم مصرح له بإنشاء مستودع لهذه الشركة
            if ($warehouseCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                throw new \Illuminate\Auth\Access\AuthorizationException("يمكنك فقط إنشاء مستودعات لشركتك الحالية.");
            }

            $data['company_id'] = $warehouseCompanyId;
            $data['created_by'] = $authUser->id;

            return Warehouse::create($data);
        });
    }
}

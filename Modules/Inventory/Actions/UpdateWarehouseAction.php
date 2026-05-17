<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * أكشن تحديث بيانات مستودع
 */
class UpdateWarehouseAction extends BaseAction
{
    public function handle(array $data = []): Warehouse
    {
        $warehouse = $data['warehouse'];
        unset($data['warehouse']);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من الصلاحيات
        $canUpdate = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            $canUpdate = true;
        } elseif ($authUser->hasAnyPermission([perm_key('warehouses.update_all'), perm_key('admin.company')])) {
            $canUpdate = $warehouse->belongsToCurrentCompany();
        } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_children'))) {
            $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_self'))) {
            $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
        }

        if (!$canUpdate) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لتحديث هذا المستودع.");
        }

        return DB::transaction(function () use ($data, $warehouse, $authUser) {
            // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
            if (isset($data['company_id']) && $data['company_id'] != $warehouse->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                throw new \Illuminate\Auth\Access\AuthorizationException("لا يمكنك تغيير شركة المستودع.");
            }

            // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالمستودع الحالي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($data['company_id'])) {
                unset($data['company_id']);
            }

            $warehouse->update($data);
            return $warehouse;
        });
    }
}

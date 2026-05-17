<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * أكشن حذف مستودع
 */
class DeleteWarehouseAction extends BaseAction
{
    public function handle(array $data = []): bool
    {
        $warehouse = $data['warehouse'];
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من الصلاحيات
        $canDelete = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            $canDelete = true;
        } elseif ($authUser->hasAnyPermission([perm_key('warehouses.delete_all'), perm_key('admin.company')])) {
            $canDelete = $warehouse->belongsToCurrentCompany();
        } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_children'))) {
            $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_self'))) {
            $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
        }

        if (!$canDelete) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لحذف هذا المستودع.");
        }

        return DB::transaction(function () use ($warehouse) {
            if ($warehouse->stocks()->exists()) {
                throw new \Exception("لا يمكن حذف المستودع. إنه يحتوي على سجلات مخزون مرتبطة.");
            }

            return $warehouse->delete();
        });
    }
}
